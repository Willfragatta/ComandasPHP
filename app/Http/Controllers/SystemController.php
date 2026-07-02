<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class SystemController extends Controller
{
    /**
     * Rota de saúde
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'OK',
            'timestamp' => now()->toIso8601String(),
            'version' => '1.0.0',
            'environment' => config('app.env', 'development'),
        ]);
    }

    /**
     * Rota de informações do sistema
     */
    public function info(): JsonResponse
    {
        return response()->json([
            'name' => 'Sistema de Comandas Mobile',
            'version' => '1.0.0',
            'description' => 'API para gerenciamento de comandas de padaria',
            'database' => [
                'host' => config('database.connections.pgsql.host'),
                'name' => config('database.connections.pgsql.database'),
                'port' => config('database.connections.pgsql.port'),
            ],
            'pasta_rede' => config('comandas.pasta_rede'),
            'cliente_padrao' => config('comandas.cliente_padrao'),
        ]);
    }
}
