<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class NetworkFolderMonitor
{
  private string $pastaRede;
  private int $intervaloMonitoramento;
  private bool $timerAtivo = false;
  private array $arquivosConhecidos = [];

  /** @var callable|null */
  private $onArquivoNovoCallback = null;

  /** @var callable|null */
  private $onArquivoRemovidoCallback = null;

  public function __construct()
  {
    $this->pastaRede = config('comandas.pasta_rede', 'C:\\WRPDV\\serverun\\Exp\\pv');
    $this->intervaloMonitoramento = (int) config('comandas.monitor_intervalo', 5);
  }

  public function onArquivoNovo(callable $callback): self
  {
    $this->onArquivoNovoCallback = $callback;

    return $this;
  }

  public function onArquivoRemovido(callable $callback): self
  {
    $this->onArquivoRemovidoCallback = $callback;

    return $this;
  }

  public function iniciarMonitoramento(): void
  {
    Log::info("Iniciando monitoramento da pasta: {$this->pastaRede}");

    if (!is_dir($this->pastaRede)) {
      Log::warning("Pasta de rede não encontrada: {$this->pastaRede}");

      return;
    }

    $this->carregarArquivosExistentes();
    $this->timerAtivo = true;

    if (php_sapi_name() === 'cli') {
      while ($this->timerAtivo) {
        $this->verificarMudancas();
        sleep($this->intervaloMonitoramento);
      }
    } else {
      Log::info('Monitoramento registrado. Chame tick() periodicamente ou execute via comando Artisan em CLI.');
    }

    Log::info('Monitoramento iniciado com sucesso');
  }

  public function pararMonitoramento(): void
  {
    $this->timerAtivo = false;
    Log::info('Monitoramento parado');
  }

  public function tick(): void
  {
    $this->verificarMudancas();
  }

  private function carregarArquivosExistentes(): void
  {
    try {
      $arquivos = scandir($this->pastaRede);
      $arquivosVd = array_filter($arquivos, function ($arquivo) {
        return str_starts_with($arquivo, 'VD') && str_ends_with($arquivo, '.TXT');
      });

      foreach ($arquivosVd as $arquivo) {
        $this->arquivosConhecidos[$arquivo] = true;
      }

      Log::info('Carregados ' . count($arquivosVd) . ' arquivos VD existentes');
    } catch (\Throwable $error) {
      Log::error('Erro ao carregar arquivos existentes: ' . $error->getMessage());
    }
  }

  private function verificarMudancas(): void
  {
    try {
      if (!is_dir($this->pastaRede)) {
        return;
      }

      $arquivos = scandir($this->pastaRede);
      $arquivosVd = array_values(array_filter($arquivos, function ($arquivo) {
        return str_starts_with($arquivo, 'VD') && str_ends_with($arquivo, '.TXT');
      }));

      foreach ($arquivosVd as $arquivo) {
        if (!isset($this->arquivosConhecidos[$arquivo])) {
          $this->arquivosConhecidos[$arquivo] = true;
          $this->processarArquivoNovo($arquivo);
        }
      }

      $arquivosRemovidos = [];
      foreach (array_keys($this->arquivosConhecidos) as $arquivo) {
        if (!in_array($arquivo, $arquivosVd, true)) {
          unset($this->arquivosConhecidos[$arquivo]);
          $arquivosRemovidos[] = $arquivo;
        }
      }

      foreach ($arquivosRemovidos as $arquivo) {
        $this->processarArquivoRemovido($arquivo);
      }
    } catch (\Throwable $error) {
      Log::error('Erro ao verificar mudanças: ' . $error->getMessage());
    }
  }

  private function processarArquivoNovo(string $arquivo): void
  {
    try {
      $numeroComanda = str_replace(['VD', '.TXT'], '', $arquivo);
      $caminhoArquivo = $this->pastaRede . DIRECTORY_SEPARATOR . $arquivo;
      $stats = stat($caminhoArquivo);

      $arquivoVd = [
        'numeroComanda' => $numeroComanda,
        'caminhoArquivo' => $caminhoArquivo,
        'dataModificacao' => Carbon::createFromTimestamp($stats['mtime']),
        'tamanhoArquivo' => $stats['size'],
      ];

      Log::info("Arquivo VD novo detectado: {$arquivo}");

      if ($this->onArquivoNovoCallback) {
        ($this->onArquivoNovoCallback)($arquivoVd);
      }
    } catch (\Throwable $error) {
      Log::error("Erro ao processar arquivo novo {$arquivo}: " . $error->getMessage());
    }
  }

  private function processarArquivoRemovido(string $arquivo): void
  {
    try {
      $numeroComanda = str_replace(['VD', '.TXT'], '', $arquivo);

      Log::info("Arquivo VD removido detectado: {$arquivo}");

      if ($this->onArquivoRemovidoCallback) {
        ($this->onArquivoRemovidoCallback)([
          'numeroComanda' => $numeroComanda,
          'arquivo' => $arquivo,
        ]);
      }
    } catch (\Throwable $error) {
      Log::error("Erro ao processar arquivo removido {$arquivo}: " . $error->getMessage());
    }
  }

  public function listarArquivosVd(): array
  {
    try {
      if (!is_dir($this->pastaRede)) {
        return [];
      }

      $arquivos = scandir($this->pastaRede);
      $arquivosVd = [];

      foreach ($arquivos as $arquivo) {
        if (!str_starts_with($arquivo, 'VD') || !str_ends_with($arquivo, '.TXT')) {
          continue;
        }

        try {
          $numeroComanda = str_replace(['VD', '.TXT'], '', $arquivo);
          $caminhoArquivo = $this->pastaRede . DIRECTORY_SEPARATOR . $arquivo;
          $stats = stat($caminhoArquivo);

          $arquivosVd[] = [
            'numeroComanda' => $numeroComanda,
            'caminhoArquivo' => $caminhoArquivo,
            'dataModificacao' => Carbon::createFromTimestamp($stats['mtime']),
            'tamanhoArquivo' => $stats['size'],
          ];
        } catch (\Throwable $error) {
          Log::error("Erro ao processar arquivo {$arquivo}: " . $error->getMessage());
        }
      }

      usort($arquivosVd, function ($a, $b) {
        return (int) $a['numeroComanda'] <=> (int) $b['numeroComanda'];
      });

      return $arquivosVd;
    } catch (\Throwable $error) {
      Log::error('Erro ao listar arquivos VD: ' . $error->getMessage());

      return [];
    }
  }

  public function verificarArquivoExiste(string $numeroComanda): bool
  {
    $caminhoArquivo = $this->pastaRede . DIRECTORY_SEPARATOR . "VD{$numeroComanda}.TXT";

    return file_exists($caminhoArquivo);
  }

  public function obterInformacoesArquivo(string $numeroComanda): ?array
  {
    try {
      $arquivo = "VD{$numeroComanda}.TXT";
      $caminhoArquivo = $this->pastaRede . DIRECTORY_SEPARATOR . $arquivo;

      if (!file_exists($caminhoArquivo)) {
        return null;
      }

      $stats = stat($caminhoArquivo);

      return [
        'numeroComanda' => $numeroComanda,
        'caminhoArquivo' => $caminhoArquivo,
        'dataModificacao' => Carbon::createFromTimestamp($stats['mtime']),
        'tamanhoArquivo' => $stats['size'],
      ];
    } catch (\Throwable $error) {
      Log::error("Erro ao obter informações do arquivo {$numeroComanda}: " . $error->getMessage());

      return null;
    }
  }
}
