<?php

namespace App\Services;

use App\Models\ProdutoPadariaModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductSyncService
{
  /** @var bool */
  private static $isRunning = false;

  /**
   * Inicia sincronização automática.
   * Executa uma vez na inicialização; agende em app/Console/Kernel.php com:
   * $schedule->command('comandas:sync-produtos')->everyTwoHours();
   */
  public static function iniciarSincronizacaoAutomatica(): void
  {
    Log::info('Iniciando sincronização automática de produtos...');
    self::sincronizarProdutos();
  }

  public static function sincronizarProdutos(): array
  {
    if (self::$isRunning) {
      Log::info('Sincronização já em andamento...');

      return ['total' => 0, 'ativos' => 0, 'comBarras' => 0];
    }

    self::$isRunning = true;

    try {
      Log::info('Iniciando sincronização de produtos...');
      $estatisticas = ProdutoPadariaModel::importarProdutos();
      Log::info('Sincronização concluída', $estatisticas);

      return $estatisticas;
    } catch (\Throwable $error) {
      Log::error('Erro na sincronização: ' . $error->getMessage());
      throw $error;
    } finally {
      self::$isRunning = false;
    }
  }

  public static function verificarAtualizacoes(): bool
  {
    try {
      $departamentos = config('comandas.departamentos', ['027', '029']);
      $placeholders = implode(', ', array_fill(0, count($departamentos), '?'));

      $query = "
        SELECT MAX(p.prod_dataalt) as ultima_atualizacao
        FROM produtos p
        WHERE p.prod_dpto_codigo IN ({$placeholders})
          AND p.prod_status = 'N'
      ";

      $result = DB::select($query, $departamentos);
      $ultimaAtualizacao = $result[0]->ultima_atualizacao ?? null;

      if (!$ultimaAtualizacao) {
        return false;
      }

      $resultLocal = DB::select('SELECT MAX(data_atualizacao) as ultima_sincronizacao FROM produtos_padaria');
      $ultimaSincronizacao = $resultLocal[0]->ultima_sincronizacao ?? null;

      if (!$ultimaSincronizacao) {
        return true;
      }

      return strtotime($ultimaAtualizacao) > strtotime($ultimaSincronizacao);
    } catch (\Throwable $error) {
      Log::error('Erro ao verificar atualizações: ' . $error->getMessage());

      return false;
    }
  }
}
