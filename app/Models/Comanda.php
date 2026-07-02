<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class ComandaModel
{
  public static function criar(array $comanda): object
  {
    $query = '
      INSERT INTO comandas_mobile
      (numero_comanda, cliente_codigo, status, total_valor, total_peso, observacoes, usuario_id, usuario_nome, usuario_codigo)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
      RETURNING *
    ';

    $values = [
      $comanda['numero_comanda'],
      $comanda['cliente_codigo'] ?? config('comandas.cliente_padrao', '113727'),
      $comanda['status'] ?? 'ATIVA',
      $comanda['total_valor'] ?? 0,
      $comanda['total_peso'] ?? 0,
      $comanda['observacoes'] ?? null,
      $comanda['usuario_id'] ?? null,
      $comanda['usuario_nome'] ?? null,
      $comanda['usuario_codigo'] ?? null,
    ];

    $result = DB::select($query, $values);

    return $result[0];
  }

  public static function buscarPorNumero(string $numero): ?object
  {
    $query = "
      SELECT
        *,
        (data_criacao AT TIME ZONE 'UTC' AT TIME ZONE 'America/Sao_Paulo')::timestamp as data_criacao_brasilia
      FROM comandas_mobile
      WHERE numero_comanda = ?
      ORDER BY data_criacao DESC, id DESC
      LIMIT 1
    ";
    $result = DB::select($query, [$numero]);

    if (empty($result)) {
      return null;
    }

    $row = (array) $result[0];
    $row['data_criacao'] = $row['data_criacao_brasilia'] ?? $row['data_criacao'];

    return (object) $row;
  }

  public static function buscarAtivaPorNumero(string $numero): ?object
  {
    $query = "
      SELECT
        *,
        (data_criacao AT TIME ZONE 'UTC' AT TIME ZONE 'America/Sao_Paulo')::timestamp as data_criacao_brasilia
      FROM comandas_mobile
      WHERE numero_comanda = ? AND status = 'ATIVA'
      ORDER BY data_criacao DESC, id DESC
      LIMIT 1
    ";
    $result = DB::select($query, [$numero]);

    if (empty($result)) {
      return null;
    }

    $row = (array) $result[0];
    $row['data_criacao'] = $row['data_criacao_brasilia'] ?? $row['data_criacao'];

    return (object) $row;
  }

  public static function listarAtivas(): array
  {
    $query = "
      SELECT
        *,
        (data_criacao AT TIME ZONE 'UTC' AT TIME ZONE 'America/Sao_Paulo')::timestamp as data_criacao_brasilia
      FROM comandas_mobile
      WHERE status = ?
      ORDER BY data_criacao DESC
    ";

    $result = DB::select($query, ['ATIVA']);

    return array_map(function ($row) {
      $arr = (array) $row;
      $arr['data_criacao'] = $arr['data_criacao_brasilia'] ?? $arr['data_criacao'];

      return (object) $arr;
    }, $result);
  }

  public static function atualizar(int $id, array $comanda): object
  {
    $fields = [];
    $values = [];

    foreach ($comanda as $key => $value) {
      $fields[] = "{$key} = ?";
      $values[] = $value;
    }

    $values[] = $id;

    $query = '
      UPDATE comandas_mobile
      SET ' . implode(', ', $fields) . ', data_atualizacao = CURRENT_TIMESTAMP
      WHERE id = ?
      RETURNING *
    ';

    $result = DB::select($query, $values);

    return $result[0];
  }

  public static function cancelar(int $id): object
  {
    $query = "
      UPDATE comandas_mobile
      SET status = 'CANCELADA', data_atualizacao = CURRENT_TIMESTAMP
      WHERE id = ?
      RETURNING *
    ";

    $result = DB::select($query, [$id]);

    return $result[0];
  }

  public static function buscarComItens(string $numero): ?array
  {
    $comanda = self::buscarPorNumero($numero);

    if (!$comanda) {
      return null;
    }

    $itensQuery = '
      SELECT * FROM comandas_itens
      WHERE comanda_id = ?
      ORDER BY data_inclusao ASC
    ';
    $itens = DB::select($itensQuery, [$comanda->id]);

    return [
      'comanda' => $comanda,
      'itens' => $itens,
    ];
  }

  public static function buscarComItensPorId(int $id): ?array
  {
    $query = "
      SELECT
        *,
        (data_criacao AT TIME ZONE 'UTC' AT TIME ZONE 'America/Sao_Paulo')::timestamp as data_criacao_brasilia
      FROM comandas_mobile
      WHERE id = ?
    ";

    $result = DB::select($query, [$id]);

    if (empty($result)) {
      return null;
    }

    $comandaRow = (array) $result[0];
    $comanda = (object) array_merge($comandaRow, [
      'data_criacao' => $comandaRow['data_criacao_brasilia'] ?? $comandaRow['data_criacao'],
    ]);

    $itensQuery = '
      SELECT * FROM comandas_itens
      WHERE comanda_id = ?
      ORDER BY data_inclusao ASC
    ';
    $itens = DB::select($itensQuery, [$id]);

    return [
      'comanda' => $comanda,
      'itens' => $itens,
    ];
  }
}

class ComandaItemModel
{
  public static function adicionar(array $item): object
  {
    $query = '
      INSERT INTO comandas_itens
      (comanda_id, produto_codigo, produto_barras, produto_descricao, produto_descrpdvs, produto_balanca,
       quantidade, preco_unitario, total_item, tributacao_codigo, prod_trib_codigo)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      RETURNING *
    ';

    $values = [
      $item['comanda_id'],
      $item['produto_codigo'],
      $item['produto_barras'] ?? null,
      $item['produto_descricao'] ?? null,
      $item['produto_descrpdvs'] ?? null,
      $item['produto_balanca'] ?? null,
      $item['quantidade'],
      $item['preco_unitario'],
      $item['total_item'],
      $item['tributacao_codigo'] ?? null,
      $item['prod_trib_codigo'] ?? null,
    ];

    $result = DB::select($query, $values);

    return $result[0];
  }

  public static function remover(int $id): void
  {
    DB::delete('DELETE FROM comandas_itens WHERE id = ?', [$id]);
  }

  public static function atualizar(int $id, array $item): object
  {
    $fields = [];
    $values = [];

    foreach ($item as $key => $value) {
      $fields[] = "{$key} = ?";
      $values[] = $value;
    }

    $values[] = $id;

    $query = '
      UPDATE comandas_itens
      SET ' . implode(', ', $fields) . '
      WHERE id = ?
      RETURNING *
    ';

    $result = DB::select($query, $values);

    return $result[0];
  }

  public static function buscarPorComanda(int $comandaId): array
  {
    return DB::select(
      'SELECT * FROM comandas_itens WHERE comanda_id = ? ORDER BY data_inclusao ASC',
      [$comandaId]
    );
  }

  public static function atualizarTotais(int $comandaId): void
  {
    $query = '
      UPDATE comandas_mobile
      SET
        total_valor = (
          SELECT COALESCE(SUM(total_item), 0)
          FROM comandas_itens
          WHERE comanda_id = ?
        ),
        total_peso = (
          SELECT COALESCE(SUM(quantidade), 0)
          FROM comandas_itens
          WHERE comanda_id = ?
        ),
        data_atualizacao = NOW()
      WHERE id = ?
    ';

    DB::update($query, [$comandaId, $comandaId, $comandaId]);
  }
}
