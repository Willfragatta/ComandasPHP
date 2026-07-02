<?php

namespace App\Services;

use App\Models\ProdutoPadariaModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TxtReader
{
  private string $pastaRede;

  public function __construct()
  {
    $this->pastaRede = config('comandas.pasta_rede', 'C:\\WRPDV\\serverun\\Exp\\pv');
  }

  public function lerArquivoTxt(string $numeroComanda): ?array
  {
    $nomeArquivo = "VD{$numeroComanda}.TXT";
    $caminhoArquivo = $this->pastaRede . DIRECTORY_SEPARATOR . $nomeArquivo;

    try {
      if (!file_exists($caminhoArquivo)) {
        return null;
      }

      $conteudo = file_get_contents($caminhoArquivo);

      return $this->interpretarConteudoTxt($conteudo, $numeroComanda);
    } catch (\Throwable $error) {
      Log::error("Erro ao ler arquivo TXT {$nomeArquivo}: " . $error->getMessage());

      return null;
    }
  }

  private function interpretarConteudoTxt(string $conteudo, string $numeroComanda): array
  {
    $linhas = array_filter(preg_split('/\r\n|\n|\r/', $conteudo), function ($linha) {
      return trim($linha) !== '';
    });

    $clienteCodigo = null;
    $unidadeCodigo = config('comandas.unidade_padrao', '001');
    $dataCriacao = Carbon::now();
    $itens = [];
    $totalValor = 0;

    foreach ($linhas as $linha) {
      if (str_starts_with($linha, 'CL')) {
        $clienteCodigo = trim(substr($linha, 2));
      } elseif (str_starts_with($linha, 'TR')) {
        $partes = explode(';', $linha);
        if (count($partes) >= 5) {
          $unidadeCodigo = $partes[4] ?: config('comandas.unidade_padrao', '001');
          if (count($partes) >= 7) {
            $dataStr = $partes[6];
            if ($dataStr && $dataStr !== '30/12/99') {
              try {
                $dia = substr($dataStr, 0, 2);
                $mes = substr($dataStr, 2, 2);
                $ano = '20' . substr($dataStr, 4, 2);
                $dataCriacao = Carbon::parse("{$ano}-{$mes}-{$dia}");
              } catch (\Throwable $error) {
                Log::warning('Erro ao interpretar data: ' . $error->getMessage());
              }
            }
          }
        }
      } elseif (str_starts_with($linha, 'IX')) {
        $partes = explode(';', $linha);
        if (count($partes) >= 4) {
          $codigoProduto = str_pad($partes[1], 11, '0', STR_PAD_LEFT);
          $quantidade = (int) $partes[2] / 1000;
          $valorUnitario = (int) $partes[3] / 100;

          $produto = $this->buscarInformacoesProduto($codigoProduto);
          if ($produto) {
            $item = [
              'codigo_barras' => $produto->codigo_barras ?? '',
              'codigo_interno' => $produto->codigo_interno,
              'descricao' => $produto->descricao,
              'quantidade' => $quantidade,
              'preco_unitario' => $valorUnitario,
              'total_item' => $quantidade * $valorUnitario,
              'unidade' => $produto->unidade,
            ];
            $itens[] = $item;
            $totalValor += $item['total_item'];
          }
        }
      } elseif (str_starts_with($linha, 'DT')) {
        $dataStr = substr($linha, 2);
        if ($dataStr && $dataStr !== '30/12/99') {
          try {
            $dia = substr($dataStr, 0, 2);
            $mes = substr($dataStr, 2, 2);
            $ano = '20' . substr($dataStr, 4, 2);
            $dataCriacao = Carbon::parse("{$ano}-{$mes}-{$dia}");
          } catch (\Throwable $error) {
            Log::warning('Erro ao interpretar data DT: ' . $error->getMessage());
          }
        }
      }
    }

    return [
      'numero_comanda' => $numeroComanda,
      'cliente_codigo' => $clienteCodigo,
      'unidade_codigo' => $unidadeCodigo,
      'data_criacao' => $dataCriacao,
      'itens' => $itens,
      'total_valor' => $totalValor,
      'observacoes' => null,
    ];
  }

  private function buscarInformacoesProduto(string $codigoProduto): ?object
  {
    try {
      return ProdutoPadariaModel::buscarPorCodigo($codigoProduto);
    } catch (\Throwable $error) {
      Log::error('Erro ao buscar produto: ' . $error->getMessage());

      return null;
    }
  }

  public function listarArquivosTxt(): array
  {
    try {
      if (!is_dir($this->pastaRede)) {
        return [];
      }

      $arquivos = scandir($this->pastaRede);
      $numeros = [];

      foreach ($arquivos as $arquivo) {
        if (str_starts_with($arquivo, 'VD') && str_ends_with(strtoupper($arquivo), '.TXT')) {
          $numeros[] = substr($arquivo, 2, -4);
        }
      }

      return $numeros;
    } catch (\Throwable $error) {
      Log::error('Erro ao listar arquivos TXT: ' . $error->getMessage());

      return [];
    }
  }

  public function obterInformacoesArquivo(string $numeroComanda): ?array
  {
    $nomeArquivo = "VD{$numeroComanda}.TXT";
    $caminhoArquivo = $this->pastaRede . DIRECTORY_SEPARATOR . $nomeArquivo;

    try {
      if (!file_exists($caminhoArquivo)) {
        return null;
      }

      $stats = stat($caminhoArquivo);

      return [
        'dataCriacao' => Carbon::createFromTimestamp($stats['mtime']),
        'tamanho' => $stats['size'],
      ];
    } catch (\Throwable $error) {
      Log::error("Erro ao obter informações do arquivo {$nomeArquivo}: " . $error->getMessage());

      return null;
    }
  }
}
