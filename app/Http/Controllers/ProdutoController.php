<?php

namespace App\Http\Controllers;

use App\Models\ProdutoPadariaModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProdutoController extends Controller
{
    /**
     * Buscar produto por código ou barras
     */
    public function buscar(string $codigo): JsonResponse
    {
        try {
            $produto = ProdutoPadariaModel::buscarProduto($codigo);
            if (!$produto) {
                return response()->json(['error' => 'Produto não encontrado'], 404);
            }

            return response()->json(['produto' => $produto]);
        } catch (\Throwable $error) {
            \Log::error('Erro ao buscar produto:', ['error' => $error->getMessage()]);
            return response()->json(['error' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Listar produtos (ativos ou todos)
     */
    public function listar(Request $request): JsonResponse
    {
        try {
            $busca = $request->query('busca');
            $limite = $request->query('limite', '50');
            $todos = $request->query('todos', 'false');

            if ($busca) {
                $produtos = ProdutoPadariaModel::buscarPorDescricao($busca);
            } elseif ($todos === 'true') {
                $produtos = ProdutoPadariaModel::listarTodos();
            } else {
                $produtos = ProdutoPadariaModel::listarAtivos();
            }

            $limiteNum = (int) $limite;
            $produtos = array_slice($produtos, 0, $limiteNum);

            return response()->json([
                'total' => count($produtos),
                'produtos' => $produtos,
            ]);
        } catch (\Throwable $error) {
            \Log::error('Erro ao listar produtos:', ['error' => $error->getMessage()]);
            return response()->json(['error' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Atualizar preço do produto
     */
    public function atualizarPreco(Request $request, string $codigo): JsonResponse
    {
        try {
            $precoFinal = $request->input('preco_unitario') ?? $request->input('preco');

            if (!$precoFinal || $precoFinal <= 0) {
                return response()->json(['error' => 'Preço inválido'], 400);
            }

            $produto = ProdutoPadariaModel::atualizarPreco($codigo, $precoFinal);
            if (!$produto) {
                return response()->json(['error' => 'Produto não encontrado'], 404);
            }

            return response()->json([
                'message' => 'Preço atualizado com sucesso',
                'produto' => $produto,
            ]);
        } catch (\Throwable $error) {
            \Log::error('Erro ao atualizar preço:', ['error' => $error->getMessage()]);
            return response()->json(['error' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Atualizar produto completo
     */
    public function atualizar(Request $request, string $codigo): JsonResponse
    {
        try {
            $produto = ProdutoPadariaModel::atualizar($codigo, [
                'descricao' => $request->input('descricao'),
                'codigo_barras' => $request->input('codigo_barras'),
                'preco_unitario' => $request->input('preco_unitario'),
                'unidade' => $request->input('unidade'),
                'tributacao_codigo' => $request->input('tributacao_codigo'),
                'ativo' => $request->input('ativo'),
            ]);

            if (!$produto) {
                return response()->json(['error' => 'Produto não encontrado'], 404);
            }

            return response()->json([
                'message' => 'Produto atualizado com sucesso',
                'produto' => $produto,
            ]);
        } catch (\Throwable $error) {
            \Log::error('Erro ao atualizar produto:', ['error' => $error->getMessage()]);
            return response()->json(['error' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Importar produtos do departamento 027
     */
    public function importar(): JsonResponse
    {
        try {
            $estatisticas = ProdutoPadariaModel::importarProdutos();

            return response()->json([
                'message' => 'Produtos importados com sucesso',
                'estatisticas' => $estatisticas,
            ]);
        } catch (\Throwable $error) {
            \Log::error('Erro ao importar produtos:', ['error' => $error->getMessage()]);
            return response()->json(['error' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Obter estatísticas dos produtos
     */
    public function estatisticas(): JsonResponse
    {
        try {
            $estatisticas = ProdutoPadariaModel::obterEstatisticas();

            return response()->json($estatisticas);
        } catch (\Throwable $error) {
            \Log::error('Erro ao obter estatísticas:', ['error' => $error->getMessage()]);
            return response()->json(['error' => 'Erro interno do servidor'], 500);
        }
    }
}
