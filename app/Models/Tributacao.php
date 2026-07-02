<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class TributacaoModel
{
  public static function buscarPorCodigo(string $codigo): ?object
  {
    $result = DB::select(
      'SELECT * FROM tributacao WHERE trib_codigo = ?',
      [$codigo]
    );

    return $result[0] ?? null;
  }

  public static function listar(): array
  {
    return DB::select('SELECT * FROM tributacao ORDER BY trib_codigo');
  }
}
