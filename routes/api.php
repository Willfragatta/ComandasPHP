<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ComandaController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::post('/comandas', [ComandaController::class, 'criar']);
Route::get('/comandas/rede', [ComandaController::class, 'listarDaPastaRede']);
Route::get('/comandas', [ComandaController::class, 'listar']);
Route::get('/comandas/{numero}', [ComandaController::class, 'buscar']);
Route::put('/comandas/{numero}', [ComandaController::class, 'editar']);
Route::post('/comandas/{numero}/itens', [ComandaController::class, 'adicionarItem']);
Route::delete('/comandas/{numero}/itens/{itemId}', [ComandaController::class, 'removerItem']);
Route::post('/comandas/{numero}/salvar-txt', [ComandaController::class, 'salvarTXT']);
Route::post('/comandas/processar-etiqueta', [ComandaController::class, 'processarEtiquetaBalanca']);
Route::delete('/comandas/{numero}', [ComandaController::class, 'cancelar']);

Route::get('/produtos', [ProdutoController::class, 'listar']);
Route::get('/produtos/estatisticas', [ProdutoController::class, 'estatisticas']);
Route::post('/produtos/importar', [ProdutoController::class, 'importar']);
Route::get('/produtos/{codigo}', [ProdutoController::class, 'buscar']);
Route::put('/produtos/{codigo}/preco', [ProdutoController::class, 'atualizarPreco']);
Route::put('/produtos/{codigo}', [ProdutoController::class, 'atualizar']);

Route::get('/usuarios', [UsuarioController::class, 'buscar']);
Route::get('/usuarios/codigo/{codigo}', [UsuarioController::class, 'buscarPorCodigo']);
Route::get('/usuarios/todos', [UsuarioController::class, 'listarTodos']);

Route::get('/admin/estatisticas', [AdminController::class, 'estatisticas']);
Route::get('/admin/vendedores/ranking', [AdminController::class, 'rankingVendedores']);
Route::get('/admin/produtos/ranking', [AdminController::class, 'produtosVendidos']);
Route::get('/admin/gamificacao', [AdminController::class, 'gamificacao']);
Route::get('/admin/usuarios', [AdminController::class, 'listarUsuarios']);
Route::post('/admin/usuarios', [UsuarioController::class, 'criar']);
Route::put('/admin/usuarios/{id}', [UsuarioController::class, 'atualizar']);
Route::put('/admin/usuarios/{id}/desativar', [UsuarioController::class, 'desativar']);
Route::put('/admin/usuarios/{id}/ativar', [UsuarioController::class, 'ativar']);
