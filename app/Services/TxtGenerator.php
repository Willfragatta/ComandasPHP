<?php

namespace App\Services;

use App\Models\TributacaoModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TxtGenerator
{
  private string $pastaRede;
  private string $clientePadrao;
  private string $unidadePadrao;

  public function __construct()
  {
    $this->pastaRede = config('comandas.pasta_rede', 'C:\\WRPDV\\serverun\\Exp\\pv');
    $this->clientePadrao = config('comandas.cliente_padrao', '113727');
    $this->unidadePadrao = config('comandas.unidade_padrao', '001');
  }

  public function gerarArquivoTxt(array $comanda): string
  {
    $nomeArquivo = "VD{$comanda['numero_comanda']}.TXT";
    $caminhoArquivo = $this->pastaRede . DIRECTORY_SEPARATOR . $nomeArquivo;

    if (file_exists($caminhoArquivo)) {
      throw new \RuntimeException("Comanda {$comanda['numero_comanda']} já existe na pasta de rede");
    }

    $conteudoTxt = $this->gerarConteudoTxt($comanda);

    try {
      if (!is_dir($this->pastaRede)) {
        mkdir($this->pastaRede, 0755, true);
      }

      file_put_contents($caminhoArquivo, $conteudoTxt);
      Log::info("Arquivo TXT gerado: {$caminhoArquivo}");

      return $caminhoArquivo;
    } catch (\Throwable $error) {
      Log::error('Erro ao gerar arquivo TXT: ' . $error->getMessage());
      throw new \RuntimeException('Erro ao salvar arquivo TXT: ' . $error->getMessage());
    }
  }

  public function atualizarArquivoTxt(array $comanda): string
  {
    $nomeArquivo = "VD{$comanda['numero_comanda']}.TXT";
    $caminhoArquivo = $this->pastaRede . DIRECTORY_SEPARATOR . $nomeArquivo;
    $conteudoTxt = $this->gerarConteudoTxt($comanda);

    try {
      if (!is_dir($this->pastaRede)) {
        mkdir($this->pastaRede, 0755, true);
      }

      file_put_contents($caminhoArquivo, $conteudoTxt);
      Log::info("Arquivo TXT atualizado: {$caminhoArquivo}");

      return $caminhoArquivo;
    } catch (\Throwable $error) {
      Log::error('Erro ao atualizar arquivo TXT: ' . $error->getMessage());
      throw new \RuntimeException('Erro ao atualizar arquivo TXT: ' . $error->getMessage());
    }
  }

  private function gerarConteudoTxt(array $comanda): string
  {
    $linhas = [];

    $linhaCliente = str_pad("CL{$this->clientePadrao}", 37, ' ');
    $linhas[] = $linhaCliente;

    foreach ($comanda['itens'] as $index => $item) {
      $codigoTributacao = $item['prod_trib_codigo'] ?? $item['tributacao_codigo'] ?? '123';

      if (empty($item['prod_trib_codigo'])) {
        Log::warning("Item {$item['codigo_interno']} não possui prod_trib_codigo, usando fallback: {$codigoTributacao}");
      }

      Log::info("Buscando tributação para código: {$codigoTributacao} (produto: {$item['codigo_interno']})");
      $tributacao = TributacaoModel::buscarPorCodigo($codigoTributacao);

      if (!$tributacao) {
        Log::error("Tributação não encontrada para código {$codigoTributacao} (produto: {$item['codigo_interno']})");
      } else {
        Log::info("Tributação encontrada: {$tributacao->trib_codigo} - {$tributacao->trib_descricao}");
      }

      $item['tributacao'] = $tributacao;

      $linhas[] = $this->gerarLinhaTransacao($item, $index + 1, $tributacao);
      $linhas[] = $this->gerarLinhaIndice($item, $tributacao);
      $linhas[] = $this->gerarLinhaItem($item, $tributacao);
    }

    $linhas[] = str_pad('TB000', 40, ' ');

    $dataCriacao = $comanda['data_criacao'] instanceof Carbon
      ? $comanda['data_criacao']
      : Carbon::parse($comanda['data_criacao']);
    $linhas[] = 'DT' . $this->formatarData($dataCriacao);

    return implode("\n", $linhas);
  }

  private function gerarLinhaTransacao(array $item, int $sequencial, ?object $tributacao): string
  {
    if (!$tributacao) {
      $codigoTributacao = $item['prod_trib_codigo'] ?? $item['tributacao_codigo'] ?? '123';
      Log::warning("Tributação não encontrada para código {$codigoTributacao}, usando padrão");

      return "TR;{$codigoTributacao};VendaPdvPrPrIsento;PR;001;:A:D:I:N:P:S:AA:II:DD:NN:D2:A2:I2:D3:I3:A3:N2:I4:D4:D5:I5:A4:N3:N4:D6:D7:N5:I6:N6:D8:I1:CD:::A5:;30/12/99;30/12/99;{$this->gerarNumeroTransacao()};040;0000000000;0000000000;07;0000000000;;;0000000000;0000000000;0000000000;;0000000000;Z;06;0000000000;06;0000000000;;;0000000000;0000000000;0000000000;0000000000;";
    }

    $trib_codigo = $tributacao->trib_codigo ?? $item['prod_trib_codigo'] ?? '123';

    $trib_descricao = $tributacao->trib_descricao ?? 'VendaPdvPrPrIsento';
    $trib_descricao = preg_replace('/\s+/', '', $trib_descricao);
    $trib_descricao = str_replace(['-', '%', '/'], '', $trib_descricao);

    $trib_ufdestino = $tributacao->trib_ufdestino ?? 'PR';

    $trib_pdv = $tributacao->trib_caracttrib ?? 'A;N;AA;NN;A2;A3;N2;A4;N3;N4;N5;N6;S;CD;A5';
    $trib_pdv = str_replace(';', ':', $trib_pdv);
    if (!str_starts_with($trib_pdv, ':')) {
      $trib_pdv = ':' . $trib_pdv;
    }
    if (!str_ends_with($trib_pdv, ':')) {
      $trib_pdv .= ':';
    }

    $trib_controle = $tributacao->trib_controle ?? $this->gerarNumeroTransacao();
    $trib_codnf = $tributacao->trib_codnf ?? '';
    $trib_icms_formatado = $this->formatarValor10Digitos($tributacao->trib_icms ?? null);
    $trib_codpdv = $tributacao->trib_codpdv ?? '07';
    $trib_codbenef = $tributacao->trib_codbenef ?? '';
    $trib_tribpiscofins = $tributacao->trib_tribpiscofins ?? 'Z';
    $trib_cstpis = $tributacao->trib_cstpis ?? '06';
    $trib_pis_formatado = $this->formatarValor10Digitos($tributacao->trib_pis ?? null);
    $trib_cstpis2 = $tributacao->trib_cstpis ?? '06';
    $trib_cofins_formatado = $this->formatarValor10Digitos($tributacao->trib_cofins ?? null);
    $trib_natpiscof = $tributacao->trib_natpiscof ?? '';

    return "TR;{$trib_codigo};{$trib_descricao};{$trib_ufdestino};001;{$trib_pdv};30/12/99;30/12/99;{$trib_controle};{$trib_codnf};{$trib_icms_formatado};0000000000;{$trib_codpdv};0000000000;{$trib_codbenef};0000000000;0000000000;0000000000;;0000000000;{$trib_tribpiscofins};{$trib_cstpis};{$trib_pis_formatado};{$trib_cstpis2};{$trib_cofins_formatado};{$trib_natpiscof};0000000000;0000000000;0000000000;0000000000;";
  }

  private function determinarTipoTransacao(string $tributacao): string
  {
    $mapeamento = [
      '020' => 'VendaPdvPrPr12',
      '123' => 'VendaPdvPrPrIsento',
      '8012' => 'VendaPdvPrPr12',
      '007' => 'VendaPdvPrPr',
      'IS' => 'VendaPdvPrPrIsento',
      'OT' => 'VendaPdvPrPr12',
      'AL' => 'VendaPdvPrPrCb7IsentoRepetidoExcluir',
      'ST' => 'VendaPdvPrPrCb7IsentoRepetidoExcluir',
      'IC' => 'VendaPdvPrPrCb7IsentoRepetidoExcluir',
      'IP' => 'VendaPdvPrPrCb7IsentoRepetidoExcluir',
      'PS' => 'VendaPdvPrPrCb7IsentoRepetidoExcluir',
      'CO' => 'VendaPdvPrPrCb7IsentoRepetidoExcluir',
    ];

    return $mapeamento[$tributacao] ?? 'VendaPdvPrPrCb7IsentoRepetidoExcluir';
  }

  private function mapearCodigoTributacao(string $tributacao): string
  {
    $mapeamento = [
      '020' => '020',
      '123' => '123',
      '8012' => '8012',
      '007' => '007',
      'IS' => '020',
      'OT' => '8012',
      'AL' => '003',
      'ST' => '003',
      'IC' => '003',
      'IP' => '003',
      'PS' => '003',
      'CO' => '003',
    ];

    return $mapeamento[$tributacao] ?? '003';
  }

  private function gerarLinhaIndice(array $item, ?object $tributacao): string
  {
    $codigoBarras = str_pad($item['codigo_barras'], 13, '0', STR_PAD_LEFT);
    $quantidade = str_pad((string) round($item['quantidade'] * 1000), 6, '0', STR_PAD_LEFT);
    $preco = str_pad((string) round($item['preco_unitario'] * 100), 10, '0', STR_PAD_LEFT);
    $trib_codpdv = str_pad($tributacao->trib_codpdv ?? '07', 2, '0', STR_PAD_LEFT);

    return "IX;{$codigoBarras};{$quantidade};{$preco};{$trib_codpdv};;0000000";
  }

  private function gerarLinhaItem(array $item, ?object $tributacao): string
  {
    $codigoBarras = str_pad($item['codigo_barras'], 13, '0', STR_PAD_LEFT);
    $quantidade = str_pad((string) round($item['quantidade'] * 1000), 6, '0', STR_PAD_LEFT);
    $preco = str_pad((string) round($item['preco_unitario'] * 100), 10, '0', STR_PAD_LEFT);

    $prefixo = "IT{$codigoBarras}{$quantidade}{$preco}";
    $trib_codpdv = str_pad($tributacao->trib_codpdv ?? '07', 2, '0', STR_PAD_LEFT);
    $espacosAntes = str_repeat(' ', 4);
    $espacosDepois = str_repeat(' ', 41);
    $sufixoFixo = '0000000';

    $linha = "{$prefixo}{$espacosAntes}{$trib_codpdv}{$espacosDepois}{$sufixoFixo}";

    if (strlen($linha) !== 85) {
      Log::warning('Linha IT com comprimento incorreto: ' . strlen($linha) . ' (esperado: 85)');
      $total = strlen($prefixo) + strlen($espacosAntes) + strlen($trib_codpdv) + strlen($espacosDepois) + strlen($sufixoFixo);
      $ajusteEspacos = max(0, 85 - $total);
      $espacosAjustados = str_repeat(' ', 41 + $ajusteEspacos);

      return substr("{$prefixo}{$espacosAntes}{$trib_codpdv}{$espacosAjustados}{$sufixoFixo}", 0, 85);
    }

    return $linha;
  }

  private function formatarValor10Digitos($valor): string
  {
    if ($valor === null || $valor === '' || !is_numeric($valor)) {
      return '0000000000';
    }

    $valorFormatado = (int) round((float) $valor * 10000);

    return str_pad((string) $valorFormatado, 10, '0', STR_PAD_LEFT);
  }

  private function formatarData(Carbon $data): string
  {
    return $data->format('dmy');
  }

  private function gerarNumeroTransacao(): string
  {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
  }

  public function verificarArquivoExiste(string $numeroComanda): bool
  {
    $caminhoArquivo = $this->pastaRede . DIRECTORY_SEPARATOR . "VD{$numeroComanda}.TXT";

    return file_exists($caminhoArquivo);
  }

  public function removerArquivoTxt(string $numeroComanda): bool
  {
    $caminhoArquivo = $this->pastaRede . DIRECTORY_SEPARATOR . "VD{$numeroComanda}.TXT";

    try {
      if (file_exists($caminhoArquivo)) {
        unlink($caminhoArquivo);
        Log::info("Arquivo TXT removido: {$caminhoArquivo}");

        return true;
      }

      return false;
    } catch (\Throwable $error) {
      Log::error('Erro ao remover arquivo TXT: ' . $error->getMessage());

      return false;
    }
  }

  public function listarArquivosVd(): array
  {
    try {
      if (!is_dir($this->pastaRede)) {
        return [];
      }

      $arquivos = scandir($this->pastaRede);
      $numeros = [];

      foreach ($arquivos as $arquivo) {
        if (str_starts_with($arquivo, 'VD') && str_ends_with($arquivo, '.TXT')) {
          $numeros[] = str_replace(['VD', '.TXT'], '', $arquivo);
        }
      }

      usort($numeros, function ($a, $b) {
        return (int) $a <=> (int) $b;
      });

      return $numeros;
    } catch (\Throwable $error) {
      Log::error('Erro ao listar arquivos VD: ' . $error->getMessage());

      return [];
    }
  }
}
