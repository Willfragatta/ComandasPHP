<?php

namespace App\Http\Controllers;

use App\Models\ComandaModel;
use App\Models\ComandaItemModel;
use App\Models\ProdutoPadariaModel;
use App\Services\NetworkFolderMonitor;
use App\Services\ScaleLabelReader;
use App\Services\TxtGenerator;
use App\Services\TxtReader;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ComandaController extends Controller
{
    /**
     * Converte uma data do PostgreSQL para o horário de Brasília (UTC-3)
     */
    private function converterParaHorarioBrasilia($data): \DateTime
    {
        if (!$data) {
            return new \DateTime();
        }

        if ($data instanceof \DateTimeInterface) {
            $dataObj = \DateTime::createFromInterface($data);
        } elseif (is_string($data)) {
            $dataObj = new \DateTime($data);
        } else {
            $dataObj = new \DateTime();
        }

        $offsetBrasilia = -3 * 60 * 60;
        $timestampBrasilia = $dataObj->getTimestamp() + $offsetBrasilia;

        return (new \DateTime())->setTimestamp($timestampBrasilia);
    }

    /**
     * Formata uma data para string preservando o horário de Brasília
     */
    private function formatarDataBrasilia($data): string
    {
        if (!$data) {
            $dataBrasilia = $this->converterParaHorarioBrasilia(new \DateTime());

            return $dataBrasilia->format('Y-m-d\TH:i:s');
        }

        if (is_string($data)) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $data)) {
                return $data;
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $data)) {
                return substr(str_replace(' ', 'T', $data), 0, 19);
            }

            $dataBrasilia = $this->converterParaHorarioBrasilia(new \DateTime($data));

            return $dataBrasilia->format('Y-m-d\TH:i:s');
        }

        $dataBrasilia = $this->converterParaHorarioBrasilia($data);

        return $dataBrasilia->format('Y-m-d\TH:i:s');
    }

    /**
     * Helper para converter valor para número (trata string e number)
     */
    private function converterParaNumero($valor): float
    {
        if (is_numeric($valor) && !is_string($valor)) {
            return is_nan((float) $valor) ? 0.0 : (float) $valor;
        }
        if (is_string($valor)) {
            $limpo = preg_replace('/[^\d,.-]/', '', $valor);
            $limpo = str_replace(',', '.', $limpo);
            $num = (float) $limpo;

            return is_nan($num) ? 0.0 : $num;
        }

        return 0.0;
    }

    /**
     * Atualizar totais da comanda
     */
    private function atualizarTotaisComanda(int $comandaId): void
    {
        $itens = ComandaItemModel::buscarPorComanda($comandaId);

        $totalValor = array_reduce($itens, function ($sum, $item) {
            return $sum + $this->converterParaNumero(data_get($item, 'total_item'));
        }, 0.0);

        $totalPeso = array_reduce($itens, function ($sum, $item) {
            return $sum + $this->converterParaNumero(data_get($item, 'quantidade'));
        }, 0.0);

        $totalValorNum = is_nan($totalValor) ? 0.0 : round((float) $totalValor, 2);
        $totalPesoNum = is_nan($totalPeso) ? 0.0 : round((float) $totalPeso, 3);

        ComandaModel::atualizar($comandaId, [
            'total_valor' => $totalValorNum,
            'total_peso' => $totalPesoNum,
        ]);
    }

    private function getSqlState(\Throwable $error): ?string
    {
        if ($error instanceof QueryException) {
            return $error->errorInfo[0] ?? null;
        }

        return null;
    }

    /**
     * Criar nova comanda
     */
    public function criar(Request $request): JsonResponse
    {
        $numeroComandaErro = 'desconhecido';

        try {
            Log::info('📝 Recebida requisição para criar comanda:', $request->all());

            $validator = Validator::make($request->all(), [
                'numero_comanda' => 'required|string|max:10',
                'cliente_codigo' => 'nullable|string|max:20',
                'observacoes' => 'nullable|string|max:500',
                'usuario_id' => 'nullable|integer|min:1',
                'usuario_nome' => 'nullable|string|max:100',
                'usuario_codigo' => 'nullable|string|max:10',
            ]);

            if ($validator->fails()) {
                Log::error('❌ Erro de validação:', $validator->errors()->all());

                return response()->json(['error' => $validator->errors()->first()], 400);
            }

            $value = $validator->validated();
            $numeroComandaErro = $value['numero_comanda'];
            Log::info("🔍 Verificando se comanda {$numeroComandaErro} já existe...");

            $comandaAtivaExistente = null;
            try {
                $comandaAtivaExistente = ComandaModel::buscarAtivaPorNumero($numeroComandaErro);
            } catch (\Throwable $dbError) {
                Log::error('❌ Erro ao verificar comanda existente:', ['error' => $dbError->getMessage()]);
                $sqlState = $this->getSqlState($dbError);
                if ($sqlState === '28P01' || str_contains($dbError->getMessage(), 'password')) {
                    return response()->json([
                        'error' => 'Erro de conexão com o banco de dados',
                        'details' => 'Verifique as credenciais de acesso ao banco de dados',
                    ], 500);
                }
                throw $dbError;
            }

            if ($comandaAtivaExistente) {
                Log::info("⚠️ Comanda {$numeroComandaErro} já existe e está ativa");

                return response()->json([
                    'error' => 'Comanda já existe e está ativa',
                    'comanda' => $comandaAtivaExistente,
                ], 400);
            }

            Log::info("✅ Criando nova comanda {$numeroComandaErro}...");

            $novaComanda = ComandaModel::criar([
                'numero_comanda' => $numeroComandaErro,
                'cliente_codigo' => $value['cliente_codigo'] ?? config('comandas.cliente_padrao', '113727'),
                'status' => 'ATIVA',
                'total_valor' => 0,
                'total_peso' => 0,
                'arquivo_txt_criado' => false,
                'observacoes' => $value['observacoes'] ?? null,
                'usuario_id' => $value['usuario_id'] ?? null,
                'usuario_nome' => $value['usuario_nome'] ?? null,
                'usuario_codigo' => $value['usuario_codigo'] ?? null,
            ]);

            Log::info("✅ Comanda {$numeroComandaErro} criada com sucesso! ID: " . data_get($novaComanda, 'id'));

            return response()->json([
                'message' => 'Comanda criada com sucesso',
                'comanda' => $novaComanda,
            ], 201);
        } catch (\Throwable $error) {
            Log::error('❌ Erro ao criar comanda:', ['error' => $error->getMessage()]);
            Log::error('📋 Mensagem de erro: ' . $error->getMessage());

            $sqlState = $this->getSqlState($error);
            if ($sqlState) {
                Log::error('🔢 Código de erro: ' . $sqlState);
            }

            $numeroComanda = $numeroComandaErro !== 'desconhecido'
                ? $numeroComandaErro
                : ($request->input('numero_comanda') ?? 'desconhecido');

            if ($sqlState === '28P01') {
                return response()->json([
                    'error' => 'Erro de autenticação no banco de dados',
                    'details' => 'Verifique as credenciais de acesso',
                ], 500);
            }

            if ($sqlState === '23505') {
                Log::error("⚠️ Constraint UNIQUE violada para comanda {$numeroComanda} - pode ser necessário remover constraint do banco");
                Log::error('💡 Execute: ALTER TABLE comandas_mobile DROP CONSTRAINT comandas_mobile_numero_comanda_key;');

                try {
                    $comandaVerificacao = ComandaModel::buscarAtivaPorNumero($numeroComanda);
                    if ($comandaVerificacao) {
                        return response()->json([
                            'error' => 'Comanda já existe e está ativa',
                            'comanda' => $comandaVerificacao,
                        ], 400);
                    }
                } catch (\Throwable $e) {
                    // Ignorar erro na verificação
                }

                return response()->json([
                    'error' => 'Número de comanda já existe no banco de dados',
                    'details' => 'O banco de dados possui uma restrição que impede números duplicados. Execute o script remover_constraint_unica.sql para permitir reutilização de números.',
                    'hint' => 'ALTER TABLE comandas_mobile DROP CONSTRAINT comandas_mobile_numero_comanda_key;',
                ], 400);
            }

            if ($sqlState === '23502') {
                return response()->json([
                    'error' => 'Campo obrigatório não informado',
                    'details' => 'Verifique os dados enviados',
                ], 400);
            }

            return response()->json([
                'error' => 'Erro interno do servidor',
                'details' => config('app.env') === 'development' ? $error->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Listar comandas da pasta de rede
     */
    public function listarDaPastaRede(): JsonResponse
    {
        try {
            $comandasBanco = ComandaModel::listarAtivas();

            $txtReader = new TxtReader();
            $networkMonitor = new NetworkFolderMonitor();
            $arquivosVd = $txtReader->listarArquivosTxt();

            $comandasMap = [];

            foreach ($comandasBanco as $comanda) {
                $numeroComanda = data_get($comanda, 'numero_comanda');
                $temTxt = $networkMonitor->verificarArquivoExiste($numeroComanda);

                if (!$temTxt) {
                    continue;
                }

                $resultado = ComandaModel::buscarComItens($numeroComanda);
                $itens = $resultado['itens'] ?? [];
                $totalValor = array_reduce($itens, function ($sum, $item) {
                    return $sum + $this->converterParaNumero(data_get($item, 'total_item'));
                }, 0.0);
                if ($totalValor === 0.0) {
                    $totalValor = $this->converterParaNumero(data_get($comanda, 'total_valor')) ?: 0.0;
                }

                $dataCriacaoBrasilia = data_get($comanda, 'data_criacao_brasilia');

                if ($dataCriacaoBrasilia) {
                    $dataObj = $dataCriacaoBrasilia instanceof \DateTimeInterface
                        ? \DateTime::createFromInterface($dataCriacaoBrasilia)
                        : new \DateTime($dataCriacaoBrasilia);

                    $dataFormatada = $dataObj->format('Y-m-d\TH:i:s') . '-03:00';
                } else {
                    $dataFormatada = $this->formatarDataBrasilia(data_get($comanda, 'data_criacao'));
                    if (!str_contains($dataFormatada, '+') && !str_contains(substr($dataFormatada, 10), '-')) {
                        $dataFormatada .= '-03:00';
                    }
                }

                $comandasMap[$numeroComanda] = [
                    'numero_comanda' => $numeroComanda,
                    'status' => 'ATIVA',
                    'data_criacao' => $dataFormatada,
                    'total_valor' => $totalValor,
                    'total_peso' => data_get($comanda, 'total_peso', 0),
                    'arquivo_txt_criado' => true,
                    'origem' => 'banco',
                    'cliente_codigo' => data_get($comanda, 'cliente_codigo', '113727'),
                    'observacoes' => null,
                    'usuario_id' => data_get($comanda, 'usuario_id'),
                    'usuario_nome' => data_get($comanda, 'usuario_nome'),
                    'usuario_codigo' => data_get($comanda, 'usuario_codigo'),
                ];
            }

            $comandasTxt = [];
            foreach ($arquivosVd as $numero) {
                if (isset($comandasMap[$numero])) {
                    continue;
                }

                $dadosTxt = $txtReader->lerArquivoTxt($numero);
                $infoArquivo = $txtReader->obterInformacoesArquivo($numero);

                if (!$dadosTxt) {
                    continue;
                }

                $totalValor = array_reduce($dadosTxt['itens'] ?? [], function ($sum, $item) {
                    return $sum + $this->converterParaNumero(data_get($item, 'total_item'));
                }, 0.0);

                $dataArquivo = $infoArquivo['dataCriacao'] ?? data_get($dadosTxt, 'data_criacao');
                $dataFormatada = $dataArquivo
                    ? $this->formatarDataBrasilia($dataArquivo)
                    : $this->formatarDataBrasilia(new \DateTime());

                $comandasTxt[] = [
                    'numero_comanda' => $numero,
                    'status' => 'ATIVA',
                    'data_criacao' => $dataFormatada,
                    'total_valor' => $totalValor,
                    'total_peso' => array_reduce($dadosTxt['itens'] ?? [], function ($total, $item) {
                        return $total + ((data_get($item, 'unidade') === 'KG') ? data_get($item, 'quantidade', 0) : 0);
                    }, 0.0),
                    'arquivo_txt_criado' => true,
                    'origem' => 'pasta_rede',
                    'cliente_codigo' => data_get($dadosTxt, 'cliente_codigo', '113727'),
                    'observacoes' => null,
                ];
            }

            foreach ($comandasTxt as $comanda) {
                if ($comanda) {
                    $comandasMap[$comanda['numero_comanda']] = $comanda;
                }
            }

            $comandas = array_values($comandasMap);
            usort($comandas, function ($a, $b) {
                return strtotime($b['data_criacao']) - strtotime($a['data_criacao']);
            });

            return response()->json([
                'total' => count($comandas),
                'comandas' => $comandas,
            ]);
        } catch (\Throwable $error) {
            Log::error('Erro ao listar comandas da pasta de rede:', ['error' => $error->getMessage()]);

            return response()->json(['error' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Buscar comanda por número
     */
    public function buscar(string $numero): JsonResponse
    {
        try {
            $resultado = ComandaModel::buscarComItens($numero);

            if ($resultado) {
                $comanda = $resultado['comanda'];
                $itens = $resultado['itens'];

                $totalValorCalculado = array_reduce($itens, function ($sum, $item) {
                    return $sum + $this->converterParaNumero(data_get($item, 'total_item'));
                }, 0.0);

                $totalFinal = $totalValorCalculado > 0
                    ? $totalValorCalculado
                    : ($this->converterParaNumero(data_get($comanda, 'total_valor')) ?: 0.0);

                $comandaComTotal = array_merge((array) $comanda, ['total_valor' => $totalFinal]);

                Log::info(sprintf(
                    '📊 Comanda %s: Total calculado = R$ %.2f (%d itens)',
                    data_get($comanda, 'numero_comanda'),
                    $totalFinal,
                    count($itens)
                ));

                return response()->json([
                    'comanda' => $comandaComTotal,
                    'itens' => $itens,
                ]);
            }

            $txtReader = new TxtReader();
            $dadosTxt = $txtReader->lerArquivoTxt($numero);

            if ($dadosTxt) {
                $comanda = [
                    'numero_comanda' => $dadosTxt['numero_comanda'],
                    'cliente_codigo' => $dadosTxt['cliente_codigo'],
                    'status' => 'ATIVA',
                    'data_criacao' => $dadosTxt['data_criacao'],
                    'total_valor' => $dadosTxt['total_valor'],
                    'observacoes' => $dadosTxt['observacoes'] ?? null,
                    'arquivo_txt_criado' => true,
                    'origem' => 'pasta_rede',
                ];

                $itens = [];
                foreach ($dadosTxt['itens'] ?? [] as $item) {
                    $produto = ProdutoPadariaModel::buscarPorBarras(data_get($item, 'codigo_barras'))
                        ?: ProdutoPadariaModel::buscarPorCodigo(data_get($item, 'codigo_interno'));

                    $itens[] = [
                        'produto_codigo' => data_get($item, 'codigo_interno'),
                        'produto_barras' => data_get($item, 'codigo_barras'),
                        'produto_descricao' => data_get($item, 'descricao'),
                        'produto_descrpdvs' => data_get($produto, 'prod_descrpdvs') ?: data_get($item, 'descricao'),
                        'produto_balanca' => data_get($produto, 'prod_balanca', 'N'),
                        'quantidade' => data_get($item, 'quantidade'),
                        'preco_unitario' => data_get($item, 'preco_unitario'),
                        'total_item' => data_get($item, 'total_item'),
                        'unidade' => data_get($item, 'unidade'),
                        'tributacao_codigo' => data_get($produto, 'tributacao_codigo', '123'),
                    ];
                }

                $totalValorCalculado = array_reduce($itens, function ($sum, $item) {
                    return $sum + $this->converterParaNumero(data_get($item, 'total_item'));
                }, 0.0);

                $comanda['total_valor'] = $totalValorCalculado > 0
                    ? $totalValorCalculado
                    : ($this->converterParaNumero($dadosTxt['total_valor'] ?? 0) ?: 0.0);

                Log::info(sprintf(
                    '📊 Comanda TXT %s: Total calculado = R$ %.2f (%d itens)',
                    $comanda['numero_comanda'],
                    $comanda['total_valor'],
                    count($itens)
                ));

                return response()->json([
                    'comanda' => $comanda,
                    'itens' => $itens,
                ]);
            }

            return response()->json(['error' => 'Comanda não encontrada'], 404);
        } catch (\Throwable $error) {
            Log::error('Erro ao buscar comanda:', ['error' => $error->getMessage()]);

            return response()->json(['error' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Listar comandas ativas
     */
    public function listar(): JsonResponse
    {
        try {
            $comandas = ComandaModel::listarAtivas();

            return response()->json([
                'total' => count($comandas),
                'comandas' => $comandas,
            ]);
        } catch (\Throwable $error) {
            Log::error('Erro ao listar comandas:', ['error' => $error->getMessage()]);

            return response()->json(['error' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Adicionar item à comanda
     */
    public function adicionarItem(Request $request, string $numero): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'produto_codigo' => 'required|string|max:20',
                'quantidade' => 'required|numeric|gt:0',
                'preco_unitario' => 'nullable|numeric|gt:0',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()->first()], 400);
            }

            $value = $validator->validated();

            $comanda = ComandaModel::buscarPorNumero($numero);
            if (!$comanda) {
                return response()->json(['error' => 'Comanda não encontrada'], 404);
            }

            if (data_get($comanda, 'status') !== 'ATIVA') {
                return response()->json(['error' => 'Comanda não está ativa'], 400);
            }

            $produto = ProdutoPadariaModel::buscarProduto($value['produto_codigo']);
            if (!$produto) {
                return response()->json(['error' => 'Produto não encontrado'], 404);
            }

            $precoUnitario = $value['preco_unitario'] ?? data_get($produto, 'preco_unitario');
            $totalItem = $value['quantidade'] * $precoUnitario;

            $novoItem = ComandaItemModel::adicionar([
                'comanda_id' => data_get($comanda, 'id'),
                'produto_codigo' => data_get($produto, 'codigo_interno'),
                'produto_barras' => data_get($produto, 'codigo_barras'),
                'produto_descricao' => data_get($produto, 'descricao'),
                'produto_descrpdvs' => data_get($produto, 'prod_descrpdvs') ?: data_get($produto, 'descricao'),
                'produto_balanca' => data_get($produto, 'prod_balanca', 'N'),
                'quantidade' => $value['quantidade'],
                'preco_unitario' => $precoUnitario,
                'total_item' => $totalItem,
                'tributacao_codigo' => data_get($produto, 'tributacao_codigo'),
                'prod_trib_codigo' => data_get($produto, 'prod_trib_codigo') ?: data_get($produto, 'tributacao_codigo', '123'),
            ]);

            return response()->json([
                'message' => 'Item adicionado com sucesso',
                'item' => $novoItem,
            ], 201);
        } catch (\Throwable $error) {
            Log::error('Erro ao adicionar item:', ['error' => $error->getMessage()]);

            return response()->json(['error' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Remover item da comanda
     */
    public function removerItem(string $numero, string $itemId): JsonResponse
    {
        try {
            $comanda = ComandaModel::buscarPorNumero($numero);
            if (!$comanda) {
                return response()->json(['error' => 'Comanda não encontrada'], 404);
            }

            if (data_get($comanda, 'status') !== 'ATIVA') {
                return response()->json(['error' => 'Comanda não está ativa'], 400);
            }

            ComandaItemModel::remover((int) $itemId);

            return response()->json(['message' => 'Item removido com sucesso']);
        } catch (\Throwable $error) {
            Log::error('Erro ao remover item:', ['error' => $error->getMessage()]);

            return response()->json(['error' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Salvar arquivo TXT
     */
    public function salvarTXT(string $numero): JsonResponse
    {
        try {
            $resultado = ComandaModel::buscarComItens($numero);
            if (!$resultado) {
                return response()->json(['error' => 'Comanda não encontrada'], 404);
            }

            $comanda = $resultado['comanda'];
            $itens = $resultado['itens'];

            if (count($itens) === 0) {
                return response()->json(['error' => 'Comanda não possui itens'], 400);
            }

            $itensFormatados = array_map(function ($item) {
                return [
                    'codigo_barras' => data_get($item, 'produto_barras') ?: data_get($item, 'produto_codigo'),
                    'codigo_interno' => data_get($item, 'produto_codigo'),
                    'descricao' => data_get($item, 'produto_descricao', ''),
                    'quantidade' => data_get($item, 'quantidade'),
                    'preco_unitario' => data_get($item, 'preco_unitario'),
                    'total_item' => data_get($item, 'total_item'),
                    'unidade' => 'UN',
                    'tributacao_codigo' => data_get($item, 'tributacao_codigo', '123'),
                    'prod_trib_codigo' => data_get($item, 'prod_trib_codigo') ?: data_get($item, 'tributacao_codigo', '123'),
                ];
            }, $itens);

            $comandaFormatada = [
                'numero_comanda' => data_get($comanda, 'numero_comanda'),
                'cliente_codigo' => data_get($comanda, 'cliente_codigo'),
                'unidade_codigo' => '001',
                'data_criacao' => data_get($comanda, 'data_criacao') ?: new \DateTime(),
                'itens' => $itensFormatados,
                'total_valor' => data_get($comanda, 'total_valor'),
                'observacoes' => data_get($comanda, 'observacoes'),
            ];

            $txtGenerator = new TxtGenerator();

            try {
                $caminhoArquivo = $txtGenerator->atualizarArquivoTxt($comandaFormatada);
            } catch (\Throwable $error) {
                $caminhoArquivo = $txtGenerator->gerarArquivoTxt($comandaFormatada);
            }

            ComandaModel::atualizar(data_get($comanda, 'id'), [
                'arquivo_txt_criado' => true,
                'caminho_arquivo' => $caminhoArquivo,
            ]);

            return response()->json([
                'message' => 'Arquivo TXT gerado com sucesso',
                'caminho_arquivo' => $caminhoArquivo,
            ]);
        } catch (\Throwable $error) {
            Log::error('Erro ao salvar TXT:', ['error' => $error->getMessage()]);

            return response()->json(['error' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Processar código de barras de etiqueta de balança
     */
    public function processarEtiquetaBalanca(Request $request): JsonResponse
    {
        try {
            $codigoBarras = $request->input('codigo_barras');

            if (!$codigoBarras) {
                return response()->json(['error' => 'Código de barras é obrigatório'], 400);
            }

            if (!ScaleLabelReader::ehEtiquetaBalanca($codigoBarras)) {
                return response()->json(['error' => 'Código de barras não é de etiqueta de balança'], 400);
            }

            $etiqueta = ScaleLabelReader::parsearCodigoBarras($codigoBarras);
            if (!$etiqueta) {
                return response()->json(['error' => 'Código de barras inválido'], 400);
            }

            $codigoLimpo = $etiqueta['codigoBarras'];

            Log::info("Buscando produto com código de balança: {$codigoLimpo}");

            $produtos = ProdutoPadariaModel::buscarPorCodigoBalanca($codigoLimpo);

            if (count($produtos) === 0) {
                return response()->json([
                    'error' => 'Produto não encontrado',
                    'codigo_barras' => $etiqueta['codigoBarras'],
                    'codigo_completo' => $etiqueta['codigoCompleto'],
                ], 404);
            }

            $produto = $produtos[0];
            Log::info('Produto encontrado: ' . data_get($produto, 'descricao') . ' (' . data_get($produto, 'codigo_barras') . ')');

            $quantidade = ScaleLabelReader::calcularQuantidade($etiqueta['valorTotal'], data_get($produto, 'preco_unitario'));

            return response()->json([
                'etiqueta' => [
                    'codigo_barras' => $etiqueta['codigoBarras'],
                    'valor_total' => $etiqueta['valorTotal'],
                    'digito_verificador' => $etiqueta['digitoVerificador'],
                    'codigo_completo' => $etiqueta['codigoCompleto'],
                ],
                'produto' => [
                    'codigo_interno' => data_get($produto, 'codigo_interno'),
                    'codigo_barras' => data_get($produto, 'codigo_barras'),
                    'descricao' => data_get($produto, 'descricao'),
                    'prod_descrpdvs' => data_get($produto, 'prod_descrpdvs'),
                    'prod_balanca' => data_get($produto, 'prod_balanca'),
                    'preco_unitario' => data_get($produto, 'preco_unitario'),
                    'unidade' => data_get($produto, 'unidade'),
                    'tributacao_codigo' => data_get($produto, 'tributacao_codigo'),
                ],
                'quantidade' => $quantidade,
            ]);
        } catch (\Throwable $error) {
            Log::error('Erro ao processar etiqueta de balança:', ['error' => $error->getMessage()]);

            return response()->json(['error' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Editar comanda (atualizar itens)
     */
    public function editar(Request $request, string $numero): JsonResponse
    {
        try {
            $itens = $request->input('itens', []);
            $itensNovos = $request->input('itensNovos', []);
            $itensRemovidos = $request->input('itensRemovidos', []);

            Log::info("Editando comanda {$numero}:");
            Log::info('- Itens modificados: ' . count($itens));
            Log::info('- Itens novos: ' . count($itensNovos));
            Log::info('- Itens removidos: ' . count($itensRemovidos));

            $comandaAtiva = ComandaModel::buscarAtivaPorNumero($numero);
            $resultado = null;

            if ($comandaAtiva) {
                $resultado = ComandaModel::buscarComItensPorId(data_get($comandaAtiva, 'id'));
            }

            if (!$resultado) {
                $resultado = ComandaModel::buscarComItens($numero);
            }

            if (!$resultado) {
                $txtReader = new TxtReader();
                $dadosTxt = $txtReader->lerArquivoTxt($numero);

                if (!$dadosTxt) {
                    return response()->json(['error' => 'Comanda não encontrada'], 404);
                }

                $comandaExistenteTxt = ComandaModel::buscarAtivaPorNumero($dadosTxt['numero_comanda']);
                $novaComanda = null;

                if ($comandaExistenteTxt) {
                    $novaComanda = $comandaExistenteTxt;
                    Log::info("ℹ️ Comanda {$dadosTxt['numero_comanda']} já existe no banco, usando ela para edição");
                } else {
                    try {
                        $novaComanda = ComandaModel::criar([
                            'numero_comanda' => $dadosTxt['numero_comanda'],
                            'cliente_codigo' => $dadosTxt['cliente_codigo'] ?? config('comandas.cliente_padrao', '113727'),
                            'status' => 'ATIVA',
                            'total_valor' => $dadosTxt['total_valor'],
                            'total_peso' => array_reduce($dadosTxt['itens'] ?? [], function ($sum, $item) {
                                return $sum + data_get($item, 'quantidade', 0);
                            }, 0.0),
                            'observacoes' => $dadosTxt['observacoes'] ?? null,
                            'arquivo_txt_criado' => true,
                        ]);
                    } catch (\Throwable $createError) {
                        if ($this->getSqlState($createError) === '23505') {
                            $comandaQualquer = ComandaModel::buscarPorNumero($dadosTxt['numero_comanda']);
                            if ($comandaQualquer) {
                                ComandaModel::atualizar(data_get($comandaQualquer, 'id'), ['status' => 'ATIVA']);
                                $comandaReativada = ComandaModel::buscarPorNumero($dadosTxt['numero_comanda']);
                                if (!$comandaReativada) {
                                    throw new \RuntimeException('Erro ao reativar comanda');
                                }
                                $novaComanda = $comandaReativada;
                                Log::info("ℹ️ Comanda {$dadosTxt['numero_comanda']} estava cancelada, reativada para edição");
                            } else {
                                throw $createError;
                            }
                        } else {
                            throw $createError;
                        }
                    }
                }

                if (!$novaComanda || !data_get($novaComanda, 'id')) {
                    return response()->json(['error' => 'Erro ao obter comanda para edição'], 500);
                }

                foreach ($dadosTxt['itens'] ?? [] as $item) {
                    $produto = ProdutoPadariaModel::buscarPorBarras(data_get($item, 'codigo_barras'));

                    ComandaItemModel::adicionar([
                        'comanda_id' => data_get($novaComanda, 'id'),
                        'produto_codigo' => data_get($produto, 'codigo_interno') ?: data_get($item, 'codigo_interno'),
                        'produto_barras' => data_get($item, 'codigo_barras'),
                        'produto_descricao' => data_get($item, 'descricao'),
                        'produto_descrpdvs' => data_get($produto, 'prod_descrpdvs') ?: data_get($item, 'descricao'),
                        'produto_balanca' => data_get($produto, 'prod_balanca', 'N'),
                        'quantidade' => data_get($item, 'quantidade'),
                        'preco_unitario' => data_get($item, 'preco_unitario'),
                        'total_item' => data_get($item, 'total_item'),
                        'tributacao_codigo' => data_get($produto, 'tributacao_codigo', '123'),
                        'prod_trib_codigo' => data_get($produto, 'prod_trib_codigo') ?: data_get($produto, 'tributacao_codigo', '123'),
                    ]);
                }

                foreach ($itens as $item) {
                    ComandaItemModel::atualizar(data_get($item, 'id'), [
                        'quantidade' => data_get($item, 'quantidade'),
                        'preco_unitario' => data_get($item, 'preco_unitario'),
                        'total_item' => data_get($item, 'total_item'),
                    ]);
                }

                foreach ($itensNovos as $item) {
                    $produto = ProdutoPadariaModel::buscarPorBarras(data_get($item, 'codigo_barras') ?: data_get($item, 'produto_codigo'))
                        ?: ProdutoPadariaModel::buscarPorCodigo(data_get($item, 'codigo_interno') ?: data_get($item, 'produto_codigo'));

                    ComandaItemModel::adicionar([
                        'comanda_id' => data_get($novaComanda, 'id'),
                        'produto_codigo' => data_get($item, 'codigo_interno') ?: data_get($item, 'produto_codigo'),
                        'produto_barras' => data_get($item, 'codigo_barras') ?: data_get($item, 'produto_codigo'),
                        'produto_descricao' => data_get($produto, 'descricao') ?: data_get($item, 'descricao'),
                        'produto_descrpdvs' => data_get($produto, 'prod_descrpdvs') ?: data_get($item, 'prod_descrpdvs') ?: data_get($item, 'descricao'),
                        'produto_balanca' => data_get($produto, 'prod_balanca') ?: data_get($item, 'prod_balanca', 'N'),
                        'quantidade' => data_get($item, 'quantidade'),
                        'preco_unitario' => data_get($item, 'preco_unitario'),
                        'total_item' => data_get($item, 'total_item'),
                        'tributacao_codigo' => data_get($produto, 'tributacao_codigo') ?: data_get($item, 'tributacao_codigo', '123'),
                        'prod_trib_codigo' => data_get($produto, 'prod_trib_codigo') ?: data_get($produto, 'tributacao_codigo', '123'),
                    ]);
                }

                foreach ($itensRemovidos as $id) {
                    Log::info("🗑️ Removendo item ID {$id} da comanda {$numero} (criada do TXT)");
                    ComandaItemModel::remover($id);
                }

                $this->atualizarTotaisComanda(data_get($novaComanda, 'id'));

                $comandaAtualizadaTxt = ComandaModel::buscarComItensPorId(data_get($novaComanda, 'id'));

                if (!$comandaAtualizadaTxt) {
                    Log::error("Erro: Não foi possível buscar comanda {$numero} após atualização");

                    return response()->json(['error' => 'Erro ao buscar comanda atualizada'], 500);
                }

                $comanda = $comandaAtualizadaTxt['comanda'];
                $itensComanda = $comandaAtualizadaTxt['itens'];

                $totalValorCalculado = array_reduce($itensComanda, function ($sum, $item) {
                    return $sum + $this->converterParaNumero(data_get($item, 'total_item'));
                }, 0.0);

                $itensFormatados = array_map(function ($item) {
                    return [
                        'codigo_barras' => data_get($item, 'produto_barras') ?: data_get($item, 'produto_codigo'),
                        'codigo_interno' => data_get($item, 'produto_codigo'),
                        'descricao' => data_get($item, 'produto_descricao', ''),
                        'quantidade' => data_get($item, 'quantidade'),
                        'preco_unitario' => data_get($item, 'preco_unitario'),
                        'total_item' => data_get($item, 'total_item'),
                        'unidade' => 'UN',
                        'tributacao_codigo' => data_get($item, 'tributacao_codigo', '123'),
                    ];
                }, $itensComanda);

                $comandaFormatada = [
                    'numero_comanda' => data_get($comanda, 'numero_comanda'),
                    'cliente_codigo' => data_get($comanda, 'cliente_codigo') ?: config('comandas.cliente_padrao', '113727'),
                    'unidade_codigo' => '001',
                    'data_criacao' => data_get($comanda, 'data_criacao') ?: new \DateTime(),
                    'itens' => $itensFormatados,
                    'total_valor' => $totalValorCalculado,
                    'observacoes' => null,
                ];

                try {
                    $txtGenerator = new TxtGenerator();
                    $txtGenerator->atualizarArquivoTxt($comandaFormatada);
                    Log::info("Arquivo TXT atualizado com sucesso para comanda {$numero}");
                } catch (\Throwable $txtError) {
                    Log::error("Erro ao atualizar arquivo TXT para comanda {$numero}:", ['error' => $txtError->getMessage()]);
                }

                return response()->json([
                    'message' => 'Comanda atualizada com sucesso',
                    'comanda' => $comanda,
                ]);
            }

            $comanda = $resultado['comanda'];
            $itensExistentes = $resultado['itens'];

            foreach ($itens as $item) {
                ComandaItemModel::atualizar(data_get($item, 'id'), [
                    'quantidade' => data_get($item, 'quantidade'),
                    'preco_unitario' => data_get($item, 'preco_unitario'),
                    'total_item' => data_get($item, 'total_item'),
                ]);
            }

            foreach ($itensNovos as $item) {
                $produto = ProdutoPadariaModel::buscarPorBarras(data_get($item, 'codigo_barras') ?: data_get($item, 'produto_codigo'))
                    ?: ProdutoPadariaModel::buscarPorCodigo(data_get($item, 'codigo_interno') ?: data_get($item, 'produto_codigo'));

                ComandaItemModel::adicionar([
                    'comanda_id' => data_get($comanda, 'id'),
                    'produto_codigo' => data_get($item, 'codigo_interno') ?: data_get($item, 'produto_codigo'),
                    'produto_barras' => data_get($item, 'codigo_barras') ?: data_get($item, 'produto_codigo'),
                    'produto_descricao' => data_get($produto, 'descricao') ?: data_get($item, 'descricao'),
                    'produto_descrpdvs' => data_get($produto, 'prod_descrpdvs') ?: data_get($item, 'prod_descrpdvs') ?: data_get($item, 'descricao'),
                    'produto_balanca' => data_get($produto, 'prod_balanca') ?: data_get($item, 'prod_balanca', 'N'),
                    'quantidade' => data_get($item, 'quantidade'),
                    'preco_unitario' => data_get($item, 'preco_unitario'),
                    'total_item' => data_get($item, 'total_item'),
                    'tributacao_codigo' => data_get($produto, 'tributacao_codigo') ?: data_get($item, 'tributacao_codigo', '123'),
                    'prod_trib_codigo' => data_get($produto, 'prod_trib_codigo') ?: data_get($produto, 'tributacao_codigo', '123'),
                ]);
            }

            if (is_array($itensRemovidos) && count($itensRemovidos) > 0) {
                Log::info('🗑️ Removendo ' . count($itensRemovidos) . " item(ns) da comanda {$numero}:", $itensRemovidos);
                foreach ($itensRemovidos as $id) {
                    if ($id) {
                        Log::info("  - Removendo item ID {$id}");
                        ComandaItemModel::remover($id);
                    }
                }
            } else {
                Log::info("ℹ️ Nenhum item removido na comanda {$numero}");
            }

            $this->atualizarTotaisComanda(data_get($comanda, 'id'));

            $comandaAtualizada = ComandaModel::buscarComItensPorId(data_get($comanda, 'id'));

            if (!$comandaAtualizada) {
                Log::error("Erro: Não foi possível buscar comanda {$numero} após atualização");

                return response()->json(['error' => 'Erro ao buscar comanda atualizada'], 500);
            }

            $cmdAtualizada = $comandaAtualizada['comanda'];
            $itensAtualizados = $comandaAtualizada['itens'];

            $totalValorCalculado = array_reduce($itensAtualizados, function ($sum, $item) {
                return $sum + $this->converterParaNumero(data_get($item, 'total_item'));
            }, 0.0);

            $itensFormatados = array_map(function ($item) {
                return [
                    'codigo_barras' => data_get($item, 'produto_barras') ?: data_get($item, 'produto_codigo'),
                    'codigo_interno' => data_get($item, 'produto_codigo'),
                    'descricao' => data_get($item, 'produto_descricao', ''),
                    'quantidade' => data_get($item, 'quantidade'),
                    'preco_unitario' => data_get($item, 'preco_unitario'),
                    'total_item' => data_get($item, 'total_item'),
                    'unidade' => 'UN',
                    'tributacao_codigo' => data_get($item, 'tributacao_codigo', '123'),
                ];
            }, $itensAtualizados);

            $comandaFormatada = [
                'numero_comanda' => data_get($cmdAtualizada, 'numero_comanda'),
                'cliente_codigo' => data_get($cmdAtualizada, 'cliente_codigo') ?: config('comandas.cliente_padrao', '113727'),
                'unidade_codigo' => '001',
                'data_criacao' => data_get($cmdAtualizada, 'data_criacao') ?: new \DateTime(),
                'itens' => $itensFormatados,
                'total_valor' => $totalValorCalculado,
                'observacoes' => null,
            ];

            try {
                $txtGenerator = new TxtGenerator();
                $txtGenerator->atualizarArquivoTxt($comandaFormatada);
                Log::info("✅ Arquivo TXT atualizado com sucesso para comanda {$numero} (" . count($itensAtualizados) . ' itens)');
            } catch (\Throwable $txtError) {
                Log::error("❌ Erro ao atualizar arquivo TXT para comanda {$numero}:", ['error' => $txtError->getMessage()]);
            }

            return response()->json([
                'message' => 'Comanda atualizada com sucesso',
                'comanda' => $cmdAtualizada,
            ]);
        } catch (\Throwable $error) {
            Log::error('❌ Erro ao editar comanda:', ['error' => $error->getMessage()]);
            Log::error('📋 Mensagem de erro: ' . $error->getMessage());

            $sqlState = $this->getSqlState($error);
            if ($sqlState) {
                Log::error('🔢 Código de erro: ' . $sqlState);
            }

            if ($sqlState === '23505') {
                $numeroComanda = $numero ?? 'desconhecido';
                Log::error("⚠️ Constraint UNIQUE violada ao editar comanda {$numeroComanda}");

                return response()->json([
                    'error' => 'Erro ao editar comanda',
                    'details' => 'O banco de dados possui uma restrição que impede operação. Execute o script remover_constraint_unica.sql.',
                    'hint' => 'ALTER TABLE comandas_mobile DROP CONSTRAINT comandas_mobile_numero_comanda_key;',
                ], 400);
            }

            return response()->json([
                'error' => 'Erro interno do servidor',
                'details' => config('app.env') === 'development' ? $error->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Cancelar comanda
     */
    public function cancelar(string $numero): JsonResponse
    {
        try {
            $comanda = ComandaModel::buscarAtivaPorNumero($numero);

            if (!$comanda) {
                $comandaQualquer = ComandaModel::buscarPorNumero($numero);
                if (!$comandaQualquer) {
                    return response()->json(['error' => 'Comanda não encontrada'], 404);
                }

                $status = data_get($comandaQualquer, 'status');
                if ($status === 'CANCELADA' || $status === 'FINALIZADA') {
                    return response()->json([
                        'error' => 'Comanda já está ' . strtolower($status),
                        'comanda' => $comandaQualquer,
                    ], 400);
                }

                ComandaModel::cancelar(data_get($comandaQualquer, 'id'));
            } else {
                ComandaModel::cancelar(data_get($comanda, 'id'));
            }

            try {
                $txtGenerator = new TxtGenerator();
                $txtGenerator->removerArquivoTxt($numero);
            } catch (\Throwable $error) {
                Log::warning('Erro ao excluir arquivo TXT:', ['error' => $error->getMessage()]);
            }

            return response()->json(['message' => 'Comanda cancelada com sucesso']);
        } catch (\Throwable $error) {
            Log::error('Erro ao cancelar comanda:', ['error' => $error->getMessage()]);
            Log::error('Mensagem de erro: ' . $error->getMessage());

            return response()->json([
                'error' => 'Erro interno do servidor',
                'details' => config('app.env') === 'development' ? $error->getMessage() : null,
            ], 500);
        }
    }
}
