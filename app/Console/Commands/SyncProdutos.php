<?php

namespace App\Console\Commands;

use App\Services\ProductSyncService;
use Illuminate\Console\Command;

class SyncProdutos extends Command
{
    protected $signature = 'comandas:sync-produtos';

    protected $description = 'Sincroniza produtos dos departamentos configurados';

    public function handle(ProductSyncService $syncService): int
    {
        $stats = $syncService->sincronizarProdutos();
        $this->info('Sincronização concluída: ' . json_encode($stats));

        return 0;
    }
}
