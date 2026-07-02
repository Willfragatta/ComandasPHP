<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class UsuarioModel
{
  public static function buscarPorCodigo(string $codigo): ?object
  {
    $result = DB::select(
      'SELECT * FROM usuarios_comandas WHERE codigo = ? AND ativo = true',
      [$codigo]
    );

    return $result[0] ?? null;
  }

  public static function buscarPorId(int $id): ?object
  {
    $result = DB::select(
      'SELECT * FROM usuarios_comandas WHERE id = ?',
      [$id]
    );

    return $result[0] ?? null;
  }

  public static function buscar(string $busca): array
  {
    $buscaExata = trim($busca);
    $buscaCodigo = "%{$buscaExata}%";
    $buscaNome = "%{$buscaExata}%";

    $query = '
      SELECT * FROM usuarios_comandas
      WHERE ativo = true
      AND (
        codigo ILIKE ?
        OR nome ILIKE ?
        OR nome ILIKE ?
      )
      ORDER BY
        CASE
          WHEN codigo = ? THEN 1
          WHEN codigo ILIKE ? THEN 2
          WHEN nome ILIKE ? THEN 3
          ELSE 4
        END,
        nome ASC
      LIMIT 20
    ';

    return DB::select($query, [
      $buscaCodigo,
      $buscaNome,
      $buscaNome,
      $buscaExata,
      $buscaCodigo,
      $buscaNome,
    ]);
  }

  public static function listarAtivos(): array
  {
    return DB::select(
      'SELECT * FROM usuarios_comandas WHERE ativo = true ORDER BY nome ASC'
    );
  }

  public static function criar(array $usuario): object
  {
    $result = DB::select(
      'INSERT INTO usuarios_comandas (nome, codigo, ativo) VALUES (?, ?, ?) RETURNING *',
      [
        $usuario['nome'],
        $usuario['codigo'],
        $usuario['ativo'] ?? true,
      ]
    );

    return $result[0];
  }

  public static function atualizar(int $id, array $usuario): object
  {
    $fields = [];
    $values = [];

    foreach ($usuario as $key => $value) {
      $fields[] = "{$key} = ?";
      $values[] = $value;
    }

    $values[] = $id;

    $query = '
      UPDATE usuarios_comandas
      SET ' . implode(', ', $fields) . '
      WHERE id = ?
      RETURNING *
    ';

    $result = DB::select($query, $values);

    return $result[0];
  }

  public static function listarTodos(): array
  {
    return DB::select(
      'SELECT * FROM usuarios_comandas ORDER BY ativo DESC, nome ASC'
    );
  }

  public static function desativar(int $id): object
  {
    $result = DB::select(
      'UPDATE usuarios_comandas SET ativo = false WHERE id = ? RETURNING *',
      [$id]
    );

    return $result[0];
  }
}
