<?php

namespace App\Http\Controllers;

use App\Models\AdminModel;
use App\Models\UsuarioModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Buscar estatísticas gerais
     * GET /api/admin/estatisticas?periodo=hoje&offset=0&data_inicio=2025-12-01&data_fim=2025-12-31
     */
    public function estatisticas(Request $request): JsonResponse
    {
        try {
            $periodo = $request->query('periodo', 'hoje');
            $offset = $request->query('offset');
            $dataInicioParam = $request->query('data_inicio');
            $dataFimParam = $request->query('data_fim');

            $periodoOffset = 0;
            if ($offset !== null) {
                $periodoOffset = (int) $offset ?: 0;
            }

            $dataInicio = null;
            $dataFim = null;

            if ($periodo === 'customizado' && $dataInicioParam && $dataFimParam) {
                $dataInicio = new \DateTime($dataInicioParam);
                $dataFim = new \DateTime($dataFimParam);
                $dataFim->setTime(23, 59, 59, 999000);
            }

            $estatisticas = AdminModel::buscarEstatisticas($periodo, $periodoOffset, $dataInicio, $dataFim);

            return response()->json($estatisticas);
        } catch (\Throwable $error) {
            \Log::error('Erro ao buscar estatísticas:', ['error' => $error->getMessage()]);

            return response()->json([
                'erro' => 'Erro ao buscar estatísticas',
                'detalhes' => $error->getMessage(),
            ], 500);
        }
    }

    /**
     * Buscar ranking de vendedores
     * GET /api/admin/vendedores/ranking?periodo=hoje&tipo=valor&data_inicio=2025-12-01&data_fim=2025-12-31
     */
    public function rankingVendedores(Request $request): JsonResponse
    {
        try {
            $periodo = $request->query('periodo', 'hoje');
            $tipo = $request->query('tipo', 'valor');
            $dataInicioParam = $request->query('data_inicio');
            $dataFimParam = $request->query('data_fim');

            if (!in_array($periodo, ['hoje', 'semana', 'mes', 'customizado'], true)) {
                return response()->json(['erro' => 'Período inválido. Use: hoje, semana, mes ou customizado'], 400);
            }

            if (!in_array($tipo, ['valor', 'quantidade'], true)) {
                return response()->json(['erro' => 'Tipo inválido. Use: valor ou quantidade'], 400);
            }

            $dataInicio = null;
            $dataFim = null;

            if ($periodo === 'customizado') {
                if (!$dataInicioParam || !$dataFimParam) {
                    return response()->json([
                        'erro' => 'Para período customizado, forneça data_inicio e data_fim (YYYY-MM-DD)',
                    ], 400);
                }
                $dataInicio = new \DateTime($dataInicioParam);
                $dataFim = new \DateTime($dataFimParam);
                $dataFim->setTime(23, 59, 59, 999000);
            }

            $ranking = AdminModel::buscarRankingVendedores($periodo, $tipo, $dataInicio, $dataFim);

            return response()->json(['ranking' => $ranking]);
        } catch (\Throwable $error) {
            \Log::error('Erro ao buscar ranking de vendedores:', ['error' => $error->getMessage()]);

            return response()->json([
                'erro' => 'Erro ao buscar ranking',
                'detalhes' => $error->getMessage(),
            ], 500);
        }
    }

    /**
     * Buscar produtos mais vendidos
     * GET /api/admin/produtos/ranking?periodo=hoje&ordem=quantidade&data_inicio=2025-12-01&data_fim=2025-12-31
     */
    public function produtosVendidos(Request $request): JsonResponse
    {
        try {
            $periodo = $request->query('periodo', 'hoje');
            $ordem = $request->query('ordem', 'quantidade');
            $dataInicioParam = $request->query('data_inicio');
            $dataFimParam = $request->query('data_fim');

            if (!in_array($periodo, ['hoje', 'semana', 'mes', 'customizado'], true)) {
                return response()->json(['erro' => 'Período inválido. Use: hoje, semana, mes ou customizado'], 400);
            }

            if (!in_array($ordem, ['quantidade', 'valor'], true)) {
                return response()->json(['erro' => 'Ordem inválida. Use: quantidade ou valor'], 400);
            }

            $dataInicio = null;
            $dataFim = null;

            if ($periodo === 'customizado') {
                if (!$dataInicioParam || !$dataFimParam) {
                    return response()->json([
                        'erro' => 'Para período customizado, forneça data_inicio e data_fim (YYYY-MM-DD)',
                    ], 400);
                }
                $dataInicio = new \DateTime($dataInicioParam);
                $dataFim = new \DateTime($dataFimParam);
                $dataFim->setTime(23, 59, 59, 999000);
            }

            $produtos = AdminModel::buscarProdutosVendidos($periodo, $ordem, $dataInicio, $dataFim);

            return response()->json(['produtos' => $produtos]);
        } catch (\Throwable $error) {
            \Log::error('Erro ao buscar produtos vendidos:', ['error' => $error->getMessage()]);

            return response()->json([
                'erro' => 'Erro ao buscar produtos',
                'detalhes' => $error->getMessage(),
            ], 500);
        }
    }

    /**
     * Buscar gamificação (badges e hall da fama)
     * GET /api/admin/gamificacao?tipo=dia&semana=0&mes=0&data_inicio=2025-12-01&data_fim=2025-12-31
     */
    public function gamificacao(Request $request): JsonResponse
    {
        try {
            $tipo = $request->query('tipo', 'dia');
            $semana = $request->query('semana');
            $mes = $request->query('mes');
            $dataInicioParam = $request->query('data_inicio');
            $dataFimParam = $request->query('data_fim');

            if (!in_array($tipo, ['dia', 'semana', 'mes', 'customizado'], true)) {
                return response()->json(['erro' => 'Tipo inválido. Use: dia, semana, mes ou customizado'], 400);
            }

            $dataInicio = null;
            $dataFim = null;
            $semanaOffset = 0;
            $mesOffset = 0;

            if ($tipo === 'customizado') {
                if (!$dataInicioParam || !$dataFimParam) {
                    return response()->json([
                        'erro' => 'Para período customizado, forneça data_inicio e data_fim (YYYY-MM-DD)',
                    ], 400);
                }
                $dataInicio = new \DateTime($dataInicioParam);
                $dataFim = new \DateTime($dataFimParam);
                $dataFim->setTime(23, 59, 59, 999000);
            } elseif ($tipo === 'semana' && $semana !== null) {
                $semanaOffset = (int) $semana ?: 0;
            } elseif ($tipo === 'mes' && $mes !== null) {
                $mesOffset = (int) $mes ?: 0;
            }

            $dados = AdminModel::buscarGamificacao($tipo, $semanaOffset, $mesOffset, $dataInicio, $dataFim);

            return response()->json($dados);
        } catch (\Throwable $error) {
            \Log::error('Erro ao buscar gamificação:', ['error' => $error->getMessage()]);

            return response()->json([
                'erro' => 'Erro ao buscar gamificação',
                'detalhes' => $error->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar todos os usuários (para admin)
     * GET /api/admin/usuarios
     */
    public function listarUsuarios(): JsonResponse
    {
        try {
            $usuarios = UsuarioModel::listarTodos();

            return response()->json(['usuarios' => $usuarios]);
        } catch (\Throwable $error) {
            \Log::error('Erro ao listar usuários:', ['error' => $error->getMessage()]);

            return response()->json([
                'erro' => 'Erro ao listar usuários',
                'detalhes' => $error->getMessage(),
            ], 500);
        }
    }
}
