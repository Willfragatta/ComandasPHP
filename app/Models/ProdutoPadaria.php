<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class ProdutoPadariaModel
{
  public static function buscarPorCodigo(string $codigo): ?object
  {
    $result = DB::select(
      'SELECT * FROM produtos_padaria WHERE codigo_interno = ? AND ativo = true',
      [$codigo]
    );

    return $result[0] ?? null;
  }

  public static function buscarPorBarras(string $barras): ?object
  {
    $result = DB::select(
      'SELECT * FROM produtos_padaria WHERE codigo_barras = ? AND ativo = true',
      [$barras]
    );

    return $result[0] ?? null;
  }

  public static function buscarProduto(string $codigo): ?object
  {
    $produto = self::buscarPorCodigo($codigo);

    if ($produto) {
      return $produto;
    }

    $produto = self::buscarPorBarras($codigo);

    if (!$produto && preg_match('/^\d+$/', $codigo)) {
      $tamanhos = [13, 12, 11, 10, 9, 8, 7, 6, 5, 4, 3, 2];

      foreach ($tamanhos as $tamanho) {
        $codigoCompleto = str_pad($codigo, $tamanho, '0', STR_PAD_LEFT);
        $produto = self::buscarPorBarras($codigoCompleto);

        if ($produto) {
          break;
        }
      }
    }

    return $produto;
  }

  public static function buscarPorPrefixoCodigoBarras(string $prefixo): array
  {
    return DB::select(
      'SELECT * FROM produtos_padaria WHERE ativo = true AND codigo_barras LIKE ? ORDER BY codigo_barras ASC',
      ["{$prefixo}%"]
    );
  }

  public static function buscarPorCodigoBalanca(string $codigoBalanca): array
  {
    $result = DB::select(
      'SELECT * FROM produtos_padaria WHERE ativo = true AND codigo_barras LIKE ? ORDER BY codigo_barras ASC LIMIT 20',
      ["%{$codigoBalanca}%"]
    );

    if (empty($result)) {
      return [];
    }

    $resultadosFiltrados = array_values(array_filter($result, function ($p) use ($codigoBalanca) {
      $barras = $p->codigo_barras;
      $posicaoCodigo = strpos($barras, $codigoBalanca);

      if ($posicaoCodigo === false) {
        return false;
      }

      $posicaoFinal = $posicaoCodigo + strlen($codigoBalanca);

      return $posicaoFinal === strlen($barras) - 1;
    }));

    return !empty($resultadosFiltrados) ? $resultadosFiltrados : $result;
  }

  public static function listarTodos(): array
  {
    return DB::select('SELECT * FROM produtos_padaria ORDER BY descricao');
  }

  public static function listarAtivos(): array
  {
    return DB::select(
      'SELECT * FROM produtos_padaria WHERE ativo = true ORDER BY descricao ASC'
    );
  }

  public static function buscarPorDescricao(string $termo): array
  {
    return DB::select(
      'SELECT * FROM produtos_padaria WHERE ativo = true AND (descricao ILIKE ? OR codigo_barras ILIKE ?) ORDER BY descricao ASC LIMIT 50',
      ["%{$termo}%", "%{$termo}%"]
    );
  }

  public static function atualizar(string $codigo, array $dados): ?object
  {
    $updates = [];
    $values = [];

    if (array_key_exists('descricao', $dados)) {
      $updates[] = 'descricao = ?';
      $values[] = $dados['descricao'];
    }
    if (array_key_exists('codigo_barras', $dados)) {
      $updates[] = 'codigo_barras = ?';
      $values[] = $dados['codigo_barras'];
    }
    if (array_key_exists('preco_unitario', $dados)) {
      $updates[] = 'preco_unitario = ?';
      $values[] = $dados['preco_unitario'];
    }
    if (array_key_exists('unidade', $dados)) {
      $updates[] = 'unidade = ?';
      $values[] = $dados['unidade'];
    }
    if (array_key_exists('tributacao_codigo', $dados)) {
      $updates[] = 'tributacao_codigo = ?';
      $values[] = $dados['tributacao_codigo'];
    }
    if (array_key_exists('ativo', $dados)) {
      $updates[] = 'ativo = ?';
      $values[] = $dados['ativo'];
    }

    if (empty($updates)) {
      return self::buscarPorCodigo($codigo);
    }

    $updates[] = 'data_atualizacao = CURRENT_TIMESTAMP';
    $values[] = $codigo;

    $query = '
      UPDATE produtos_padaria
      SET ' . implode(', ', $updates) . '
      WHERE codigo_interno = ?
      RETURNING *
    ';

    $result = DB::select($query, $values);

    return $result[0] ?? null;
  }

  public static function atualizarPreco(string $codigo, float $novoPreco): object
  {
    $result = DB::select(
      'UPDATE produtos_padaria SET preco_unitario = ?, data_atualizacao = CURRENT_TIMESTAMP WHERE codigo_interno = ? RETURNING *',
      [$novoPreco, $codigo]
    );

    return $result[0];
  }

  public static function importarProdutos(): array
  {
    $departamentos = config('comandas.departamentos', ['027', '029']);
    $placeholders = implode(', ', array_fill(0, count($departamentos), '?'));
    $unidadePadrao = config('comandas.unidade_padrao', '001');

    $query = "
      INSERT INTO produtos_padaria (codigo_interno, codigo_barras, descricao, preco_unitario, unidade, tributacao_codigo, ativo)
      SELECT
        p.prod_codigo::VARCHAR,
        p.prod_codbarras::VARCHAR,
        p.prod_descricao,
        COALESCE(pu.prun_prvenda, 0.00) as preco_unitario,
        COALESCE(pu.prun_emb, 'UN') as unidade,
        COALESCE(pu.prun_ultsimbcomp, '123') as tributacao_codigo,
        CASE WHEN p.prod_status = 'N' THEN TRUE ELSE FALSE END as ativo
      FROM produtos p
      LEFT JOIN produn pu ON p.prod_codigo = pu.prun_prod_codigo AND pu.prun_unid_codigo = ?
      WHERE p.prod_dpto_codigo IN ({$placeholders})
        AND p.prod_status = 'N'
        AND pu.prun_ativo = 'S'
      ON CONFLICT (codigo_interno)
      DO UPDATE SET
        codigo_barras = EXCLUDED.codigo_barras,
        descricao = EXCLUDED.descricao,
        preco_unitario = EXCLUDED.preco_unitario,
        unidade = EXCLUDED.unidade,
        tributacao_codigo = EXCLUDED.tributacao_codigo,
        ativo = EXCLUDED.ativo,
        data_atualizacao = CURRENT_TIMESTAMP
    ";

    DB::statement($query, array_merge([$unidadePadrao], $departamentos));

    $updateQuery = "
      UPDATE produtos_padaria
      SET ativo = FALSE, data_atualizacao = CURRENT_TIMESTAMP
      WHERE codigo_interno NOT IN (
        SELECT p.prod_codigo::VARCHAR
        FROM produtos p
        WHERE p.prod_dpto_codigo IN ({$placeholders})
          AND p.prod_status = 'N'
      )
    ";

    DB::statement($updateQuery, $departamentos);

    return self::obterEstatisticas();
  }

  public static function obterEstatisticas(): array
  {
    $result = DB::select('
      SELECT
        COUNT(*) as total_produtos,
        COUNT(CASE WHEN ativo = TRUE THEN 1 END) as produtos_ativos,
        COUNT(CASE WHEN codigo_barras IS NOT NULL AND codigo_barras != \'\' THEN 1 END) as produtos_com_barras
      FROM produtos_padaria
    ');

    $row = (array) ($result[0] ?? []);

    return [
      'total' => (int) ($row['total_produtos'] ?? 0),
      'ativos' => (int) ($row['produtos_ativos'] ?? 0),
      'comBarras' => (int) ($row['produtos_com_barras'] ?? 0),
    ];
  }
}
