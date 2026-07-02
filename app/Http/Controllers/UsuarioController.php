<?php

namespace App\Http\Controllers;

use App\Models\UsuarioModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UsuarioController extends Controller
{
    /**
     * Buscar usuários por código ou nome
     * GET /api/usuarios?busca=termo
     */
    public function buscar(Request $request): JsonResponse
    {
        try {
            $busca = $request->query('busca');

            if (!$busca || !is_string($busca) || trim($busca) === '') {
                $usuarios = UsuarioModel::listarAtivos();

                return response()->json(['usuarios' => $usuarios]);
            }

            $termo = trim($busca);
            $usuarios = UsuarioModel::buscar($termo);

            return response()->json(['usuarios' => $usuarios]);
        } catch (\Throwable $error) {
            \Log::error('Erro ao buscar usuários:', ['error' => $error->getMessage()]);

            return response()->json([
                'erro' => 'Erro ao buscar usuários',
                'detalhes' => $error->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar todos os usuários ativos
     * GET /api/usuarios
     */
    public function listar(): JsonResponse
    {
        try {
            $usuarios = UsuarioModel::listarAtivos();

            return response()->json(['usuarios' => $usuarios]);
        } catch (\Throwable $error) {
            \Log::error('Erro ao listar usuários:', ['error' => $error->getMessage()]);

            return response()->json([
                'erro' => 'Erro ao listar usuários',
                'detalhes' => $error->getMessage(),
            ], 500);
        }
    }

    /**
     * Buscar usuário por código
     * GET /api/usuarios/codigo/:codigo
     */
    public function buscarPorCodigo(string $codigo): JsonResponse
    {
        try {
            if (!$codigo) {
                return response()->json(['erro' => 'Código do usuário é obrigatório'], 400);
            }

            $usuario = UsuarioModel::buscarPorCodigo($codigo);

            if (!$usuario) {
                return response()->json(['erro' => 'Usuário não encontrado'], 404);
            }

            return response()->json(['usuario' => $usuario]);
        } catch (\Throwable $error) {
            \Log::error('Erro ao buscar usuário por código:', ['error' => $error->getMessage()]);

            return response()->json([
                'erro' => 'Erro ao buscar usuário',
                'detalhes' => $error->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar todos os usuários (incluindo inativos)
     * GET /api/usuarios/todos
     */
    public function listarTodos(): JsonResponse
    {
        try {
            $usuarios = UsuarioModel::listarTodos();

            return response()->json(['usuarios' => $usuarios]);
        } catch (\Throwable $error) {
            \Log::error('Erro ao listar todos os usuários:', ['error' => $error->getMessage()]);

            return response()->json([
                'erro' => 'Erro ao listar usuários',
                'detalhes' => $error->getMessage(),
            ], 500);
        }
    }

    /**
     * Criar novo usuário
     * POST /api/admin/usuarios
     */
    public function criar(Request $request): JsonResponse
    {
        try {
            $nome = $request->input('nome');
            $codigo = $request->input('codigo');
            $ativo = $request->input('ativo');

            if (!$nome || !$codigo) {
                return response()->json(['erro' => 'Nome e código são obrigatórios'], 400);
            }

            $existente = DB::select('SELECT * FROM usuarios_comandas WHERE codigo = ?', [trim($codigo)]);
            if (count($existente) > 0) {
                return response()->json(['erro' => 'Código já está em uso'], 400);
            }

            $usuario = UsuarioModel::criar([
                'nome' => trim($nome),
                'codigo' => trim($codigo),
                'ativo' => $ativo !== null ? (bool) $ativo : true,
            ]);

            return response()->json(['usuario' => $usuario], 201);
        } catch (\Throwable $error) {
            \Log::error('Erro ao criar usuário:', ['error' => $error->getMessage()]);

            return response()->json([
                'erro' => 'Erro ao criar usuário',
                'detalhes' => $error->getMessage(),
            ], 500);
        }
    }

    /**
     * Atualizar usuário
     * PUT /api/admin/usuarios/:id
     */
    public function atualizar(Request $request, string $id): JsonResponse
    {
        try {
            $nome = $request->input('nome');
            $codigo = $request->input('codigo');
            $ativo = $request->input('ativo');

            if (!$id) {
                return response()->json(['erro' => 'ID do usuário é obrigatório'], 400);
            }

            $usuarioExistente = UsuarioModel::buscarPorId((int) $id);
            if (!$usuarioExistente) {
                return response()->json(['erro' => 'Usuário não encontrado'], 404);
            }

            $codigoExistente = data_get($usuarioExistente, 'codigo');

            if ($codigo && $codigo !== $codigoExistente) {
                $duplicado = DB::select(
                    'SELECT * FROM usuarios_comandas WHERE codigo = ? AND id != ?',
                    [$codigo, $id]
                );
                if (count($duplicado) > 0) {
                    return response()->json(['erro' => 'Código já está em uso por outro usuário'], 400);
                }
            }

            $dadosAtualizacao = [];
            if ($nome !== null) {
                $dadosAtualizacao['nome'] = trim($nome);
            }
            if ($codigo !== null) {
                $dadosAtualizacao['codigo'] = trim($codigo);
            }
            if ($ativo !== null) {
                $dadosAtualizacao['ativo'] = (bool) $ativo;
            }

            $usuario = UsuarioModel::atualizar((int) $id, $dadosAtualizacao);

            return response()->json(['usuario' => $usuario]);
        } catch (\Throwable $error) {
            \Log::error('Erro ao atualizar usuário:', ['error' => $error->getMessage()]);

            return response()->json([
                'erro' => 'Erro ao atualizar usuário',
                'detalhes' => $error->getMessage(),
            ], 500);
        }
    }

    /**
     * Desativar usuário
     * PUT /api/admin/usuarios/:id/desativar
     */
    public function desativar(string $id): JsonResponse
    {
        try {
            if (!$id) {
                return response()->json(['erro' => 'ID do usuário é obrigatório'], 400);
            }

            $usuario = UsuarioModel::desativar((int) $id);
            if (!$usuario) {
                return response()->json(['erro' => 'Usuário não encontrado'], 404);
            }

            return response()->json(['usuario' => $usuario]);
        } catch (\Throwable $error) {
            \Log::error('Erro ao desativar usuário:', ['error' => $error->getMessage()]);

            return response()->json([
                'erro' => 'Erro ao desativar usuário',
                'detalhes' => $error->getMessage(),
            ], 500);
        }
    }

    /**
     * Ativar usuário
     * PUT /api/admin/usuarios/:id/ativar
     */
    public function ativar(string $id): JsonResponse
    {
        try {
            if (!$id) {
                return response()->json(['erro' => 'ID do usuário é obrigatório'], 400);
            }

            $usuario = UsuarioModel::atualizar((int) $id, ['ativo' => true]);
            if (!$usuario) {
                return response()->json(['erro' => 'Usuário não encontrado'], 404);
            }

            return response()->json(['usuario' => $usuario]);
        } catch (\Throwable $error) {
            \Log::error('Erro ao ativar usuário:', ['error' => $error->getMessage()]);

            return response()->json([
                'erro' => 'Erro ao ativar usuário',
                'detalhes' => $error->getMessage(),
            ], 500);
        }
    }
}
