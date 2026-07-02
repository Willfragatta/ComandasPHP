<?php

namespace App\Providers;

use App\Services\ProductSyncService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        try {
            ProductSyncService::sincronizarProdutos();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
