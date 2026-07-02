<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminModel
{
  public static function buscarEstatisticas(
    string $periodoSelecionado = 'hoje',
    int $offset = 0,
    ?Carbon $dataInicioCustom = null,
    ?Carbon $dataFimCustom = null
  ): array {
    $hoje = Carbon::today();

    $inicioSemana = $hoje->copy()->subDays($hoje->dayOfWeek);
    $inicioMes = $hoje->copy()->startOfMonth();

    if ($periodoSelecionado === 'customizado' && $dataInicioCustom && $dataFimCustom) {
      $periodoInicio = $dataInicioCustom->copy();
      $periodoFim = $dataFimCustom->copy();
      $periodoCanceladas = $dataInicioCustom->copy();
    } elseif ($periodoSelecionado === 'hoje') {
      $periodoInicio = $hoje->copy();
      $periodoFim = $hoje->copy()->endOfDay();
      $periodoCanceladas = $hoje->copy();
    } elseif ($periodoSelecionado === 'semana') {
      $dataBase = $hoje->copy()->subDays($hoje->dayOfWeek + ($offset * 7));
      $periodoInicio = $dataBase->copy()->subDays($dataBase->dayOfWeek);
      $periodoFim = $periodoInicio->copy()->addDays(6)->endOfDay();
      $periodoCanceladas = $periodoInicio->copy();
    } elseif ($periodoSelecionado === 'mes') {
      $dataBase = $hoje->copy()->subMonths($offset)->startOfMonth();
      $periodoInicio = $dataBase->copy();
      $periodoFim = $dataBase->copy()->endOfMonth()->endOfDay();
      $periodoCanceladas = $periodoInicio->copy();
    } else {
      $periodoInicio = $hoje->copy();
      $periodoFim = $hoje->copy()->endOfDay();
      $periodoCanceladas = $hoje->copy();
    }

    $queryHoje = "
      SELECT
        COALESCE(SUM(total_valor), 0) as total_valor,
        COUNT(*) as total_comandas
      FROM comandas_mobile
      WHERE status = 'FINALIZADA'
        AND (data_criacao AT TIME ZONE 'UTC' AT TIME ZONE 'America/Sao_Paulo')::date = ?::date
    ";

    $querySemana = "
      SELECT
        COALESCE(SUM(total_valor), 0) as total_valor,
        COUNT(*) as total_comandas
      FROM comandas_mobile
      WHERE status = 'FINALIZADA'
        AND (data_criacao AT TIME ZONE 'UTC' AT TIME ZONE 'America/Sao_Paulo') >= ?
    ";

    $queryMes = "
      SELECT
        COALESCE(SUM(total_valor), 0) as total_valor,
        COUNT(*) as total_comandas
      FROM comandas_mobile
      WHERE status = 'FINALIZADA'
        AND (data_criacao AT TIME ZONE 'UTC' AT TIME ZONE 'America/Sao_Paulo') >= ?
    ";

    $queryPeriodo = "
      SELECT
        COALESCE(SUM(total_valor), 0) as total_valor,
        COUNT(*) as total_comandas
      FROM comandas_mobile
      WHERE status = 'FINALIZADA'
        AND (data_criacao AT TIME ZONE 'UTC' AT TIME ZONE 'America/Sao_Paulo')::timestamp >= ?::timestamp
        AND (data_criacao AT TIME ZONE 'UTC' AT TIME ZONE 'America/Sao_Paulo')::timestamp <= ?::timestamp
    ";

    $queryCanceladasPeriodo = "
      SELECT COUNT(*) as total_canceladas
      FROM comandas_mobile
      WHERE status = 'CANCELADA'
        AND (data_criacao AT TIME ZONE 'UTC' AT TIME ZONE 'America/Sao_Paulo')::timestamp >= ?::timestamp
        AND (data_criacao AT TIME ZONE 'UTC' AT TIME ZONE 'America/Sao_Paulo')::timestamp <= ?::timestamp
    ";

    $queryCanceladasHoje = "
      SELECT COUNT(*) as total_canceladas
      FROM comandas_mobile
      WHERE status = 'CANCELADA'
        AND (data_criacao AT TIME ZONE 'UTC' AT TIME ZONE 'America/Sao_Paulo')::date = ?::date
    ";

    $queryAtendentes = "
      SELECT COUNT(*) as total
      FROM usuarios_comandas
      WHERE ativo = true
    ";

    $resultPeriodo = DB::select($queryPeriodo, [$periodoInicio, $periodoFim]);
    $resultCanceladasPeriodo = DB::select($queryCanceladasPeriodo, [$periodoCanceladas, $periodoFim]);
    $resultHoje = DB::select($queryHoje, [$hoje]);
    $resultSemana = DB::select($querySemana, [$inicioSemana]);
    $resultMes = DB::select($queryMes, [$inicioMes]);
    $resultCanceladasHoje = DB::select($queryCanceladasHoje, [$hoje]);
    $resultAtendentes = DB::select($queryAtendentes);

    return [
      'periodo' => [
        'total_valor' => (float) ($resultPeriodo[0]->total_valor ?? 0),
        'total_comandas' => (int) ($resultPeriodo[0]->total_comandas ?? 0),
        'comandas_canceladas' => (int) ($resultCanceladasPeriodo[0]->total_canceladas ?? 0),
      ],
      'hoje' => [
        'total_valor' => (float) ($resultHoje[0]->total_valor ?? 0),
        'total_comandas' => (int) ($resultHoje[0]->total_comandas ?? 0),
        'comandas_canceladas' => (int) ($resultCanceladasHoje[0]->total_canceladas ?? 0),
      ],
      'semana' => [
        'total_valor' => (float) ($resultSemana[0]->total_valor ?? 0),
        'total_comandas' => (int) ($resultSemana[0]->total_comandas ?? 0),
      ],
      'mes' => [
        'total_valor' => (float) ($resultMes[0]->total_valor ?? 0),
        'total_comandas' => (int) ($resultMes[0]->total_comandas ?? 0),
      ],
      'atendentes_ativos' => (int) ($resultAtendentes[0]->total ?? 0),
    ];
  }

  public static function buscarRankingVendedores(
    string $periodo,
    string $tipo = 'valor',
    ?Carbon $dataInicioCustom = null,
    ?Carbon $dataFimCustom = null
  ): array {
    $hoje = Carbon::today();
    $dataFim = null;

    if ($periodo === 'customizado' && $dataInicioCustom && $dataFimCustom) {
      $dataInicio = $dataInicioCustom->copy();
      $dataFim = $dataFimCustom->copy();
    } else {
      switch ($periodo) {
        case 'hoje':
          $dataInicio = $hoje->copy();
          $dataFim = $hoje->copy()->endOfDay();
          break;
        case 'semana':
          $dataInicio = $hoje->copy()->subDays($hoje->dayOfWeek);
          $dataFim = $dataInicio->copy()->addDays(6)->endOfDay();
          break;
        case 'mes':
          $dataInicio = $hoje->copy()->startOfMonth();
          $dataFim = $hoje->copy()->endOfMonth()->endOfDay();
          break;
        default:
          $dataInicio = $hoje->copy();
          $dataFim = $hoje->copy()->endOfDay();
      }
    }

    $orderBy = $tipo === 'valor' ? 'total_valor DESC' : 'total_comandas DESC';

    if ($dataFim) {
      $query = "
        SELECT
          cm.usuario_id,
          cm.usuario_nome as usuario_nome,
          cm.usuario_codigo as usuario_codigo,
          COALESCE(SUM(cm.total_valor), 0) as total_valor,
          COUNT(*) as total_comandas,
          CASE
            WHEN COUNT(*) > 0 THEN COALESCE(SUM(cm.total_valor), 0) / COUNT(*)
            ELSE 0
          END as ticket_medio
        FROM comandas_mobile cm
        WHERE cm.status = 'FINALIZADA'
          AND cm.usuario_id IS NOT NULL
          AND (cm.data_criacao AT TIME ZONE 'UTC' AT TIME ZONE 'America/Sao_Paulo')::timestamp >= ?::timestamp
          AND (cm.data_criacao AT TIME ZONE 'UTC' AT TIME ZONE 'America/Sao_Paulo')::timestamp <= ?::timestamp
        GROUP BY cm.usuario_id, cm.usuario_nome, cm.usuario_codigo
        ORDER BY {$orderBy}
        LIMIT 10
      ";
      $params = [$dataInicio, $dataFim];
    } else {
      $query = "
        SELECT
          cm.usuario_id,
          cm.usuario_nome as usuario_nome,
          cm.usuario_codigo as usuario_codigo,
          COALESCE(SUM(cm.total_valor), 0) as total_valor,
          COUNT(*) as total_comandas,
          CASE
            WHEN COUNT(*) > 0 THEN COALESCE(SUM(cm.total_valor), 0) / COUNT(*)
            ELSE 0
          END as ticket_medio
        FROM comandas_mobile cm
        WHERE cm.status = 'FINALIZADA'
          AND cm.usuario_id IS NOT NULL
          AND (cm.data_criacao AT TIME ZONE 'UTC' AT TIME ZONE 'America/Sao_Paulo')::timestamp >= ?::timestamp
        GROUP BY cm.usuario_id, cm.usuario_nome, cm.usuario_codigo
        ORDER BY {$orderBy}
        LIMIT 10
      ";
      $params = [$dataInicio];
    }

    $result = DB::select($query, $params);

    return array_map(function ($row) {
      return [
        'usuario_id' => $row->usuario_id,
        'usuario_nome' => $row->usuario_nome ?? 'Sem nome',
        'usuario_codigo' => $row->usuario_codigo ?? 'Sem código',
        'total_valor' => (float) ($row->total_valor ?? 0),
        'total_comandas' => (int) ($row->total_comandas ?? 0),
        'ticket_medio' => (float) ($row->ticket_medio ?? 0),
      ];
    }, $result);
  }

  public static function buscarProdutosVendidos(
    string $periodo,
    string $ordem = 'quantidade',
    ?Carbon $dataInicioCustom = null,
    ?Carbon $dataFimCustom = null
  ): array {
    $hoje = Carbon::today();
    $dataFim = null;

    if ($periodo === 'customizado' && $dataInicioCustom && $dataFimCustom) {
      $dataInicio = $dataInicioCustom->copy();
      $dataFim = $dataFimCustom->copy()->endOfDay();
    } else {
      switch ($periodo) {
        case 'hoje':
          $dataInicio = $hoje->copy();
          $dataFim = $hoje->copy()->endOfDay();
          break;
        case 'semana':
          $dataInicio = $hoje->copy()->subDays($hoje->dayOfWeek);
          $dataFim = $dataInicio->copy()->addDays(6)->endOfDay();
          break;
        case 'mes':
          $dataInicio = $hoje->copy()->startOfMonth();
          $dataFim = $hoje->copy()->endOfMonth()->endOfDay();
          break;
        default:
          $dataInicio = $hoje->copy();
          $dataFim = $hoje->copy()->endOfDay();
      }
    }

    $orderBy = $ordem === 'quantidade' ? 'quantidade_total DESC' : 'valor_total DESC';

    if ($dataFim) {
      $query = "
        SELECT
          ci.produto_codigo,
          ci.produto_descricao,
          ci.produto_descricao as descricao,
          COALESCE(SUM(ci.quantidade), 0) as quantidade_total,
          COALESCE(SUM(ci.total_item), 0) as valor_total,
          MAX(pp.unidade) as unidade
        FROM comandas_itens ci
        INNER JOIN comandas_mobile cm ON ci.comanda_id = cm.id
        LEFT JOIN produtos_padaria pp ON ci.produto_codigo = pp.codigo_interno
        WHERE cm.status = 'FINALIZADA'
          AND (cm.data_criacao AT TIME ZONE 'UTC' AT TIME ZONE 'America/Sao_Paulo')::timestamp >= ?::timestamp
          AND (cm.data_criacao AT TIME ZONE 'UTC' AT TIME ZONE 'America/Sao_Paulo')::timestamp <= ?::timestamp
        GROUP BY ci.produto_codigo, ci.produto_descricao
        ORDER BY {$orderBy}
        LIMIT 20
      ";
      $params = [$dataInicio, $dataFim];
    } else {
      $query = "
        SELECT
          ci.produto_codigo,
          ci.produto_descricao,
          ci.produto_descricao as descricao,
          COALESCE(SUM(ci.quantidade), 0) as quantidade_total,
          COALESCE(SUM(ci.total_item), 0) as valor_total,
          MAX(pp.unidade) as unidade
        FROM comandas_itens ci
        INNER JOIN comandas_mobile cm ON ci.comanda_id = cm.id
        LEFT JOIN produtos_padaria pp ON ci.produto_codigo = pp.codigo_interno
        WHERE cm.status = 'FINALIZADA'
          AND (cm.data_criacao AT TIME ZONE 'UTC' AT TIME ZONE 'America/Sao_Paulo')::timestamp >= ?::timestamp
        GROUP BY ci.produto_codigo, ci.produto_descricao
        ORDER BY {$orderBy}
        LIMIT 20
      ";
      $params = [$dataInicio];
    }

    $result = DB::select($query, $params);

    return array_map(function ($row) {
      return [
        'produto_codigo' => $row->produto_codigo,
        'produto_descricao' => $row->produto_descricao ?? 'Produto sem descrição',
        'descricao' => $row->descricao ?? $row->produto_descricao ?? 'Produto sem descrição',
        'quantidade_total' => (float) ($row->quantidade_total ?? 0),
        'valor_total' => (float) ($row->valor_total ?? 0),
        'unidade' => $row->unidade ?? 'UN',
      ];
    }, $result);
  }

  public static function buscarGamificacao(
    string $tipo = 'dia',
    int $semanaOffset = 0,
    int $mesOffset = 0,
    ?Carbon $dataInicioCustom = null,
    ?Carbon $dataFimCustom = null
  ): array {
    $hoje = Carbon::today();

    if ($tipo === 'customizado' && $dataInicioCustom && $dataFimCustom) {
      $dataInicio = $dataInicioCustom->copy();
      $dataFim = $dataFimCustom->copy();
    } elseif ($tipo === 'dia') {
      $dataInicio = $hoje->copy();
      $dataFim = $hoje->copy()->endOfDay();
    } elseif ($tipo === 'semana') {
      $dataBase = $hoje->copy()->subDays($hoje->dayOfWeek + ($semanaOffset * 7));
      $dataInicio = $dataBase->copy()->subDays($dataBase->dayOfWeek);
      $dataFim = $dataInicio->copy()->addDays(6)->endOfDay();
    } else {
      $dataBase = $hoje->copy()->subMonths($mesOffset)->startOfMonth();
      $dataInicio = $dataBase->copy();
      $dataFim = $dataBase->copy()->endOfMonth()->endOfDay();
    }

    $queryBadge = "
      SELECT
        cm.usuario_id,
        cm.usuario_nome,
        cm.usuario_codigo,
        COALESCE(SUM(cm.total_valor), 0) as total_valor
      FROM comandas_mobile cm
      WHERE cm.status = 'FINALIZADA'
        AND cm.usuario_id IS NOT NULL
        AND (cm.data_criacao AT TIME ZONE 'UTC' AT TIME ZONE 'America/Sao_Paulo')::timestamp >= ?::timestamp
        AND (cm.data_criacao AT TIME ZONE 'UTC' AT TIME ZONE 'America/Sao_Paulo')::timestamp <= ?::timestamp
      GROUP BY cm.usuario_id, cm.usuario_nome, cm.usuario_codigo
      ORDER BY total_valor DESC
      LIMIT 1
    ";

    $queryHallFama = "
      SELECT
        cm.usuario_id,
        cm.usuario_nome as usuario_nome,
        cm.usuario_codigo as usuario_codigo,
        COALESCE(SUM(cm.total_valor), 0) as total_valor,
        COUNT(*) as total_comandas,
        CASE
          WHEN COUNT(*) > 0 THEN COALESCE(SUM(cm.total_valor), 0) / COUNT(*)
          ELSE 0
        END as ticket_medio
      FROM comandas_mobile cm
      WHERE cm.status = 'FINALIZADA'
        AND cm.usuario_id IS NOT NULL
        AND (cm.data_criacao AT TIME ZONE 'UTC' AT TIME ZONE 'America/Sao_Paulo')::timestamp >= ?::timestamp
        AND (cm.data_criacao AT TIME ZONE 'UTC' AT TIME ZONE 'America/Sao_Paulo')::timestamp <= ?::timestamp
      GROUP BY cm.usuario_id, cm.usuario_nome, cm.usuario_codigo
      ORDER BY total_valor DESC
      LIMIT 5
    ";

    $resultBadge = DB::select($queryBadge, [$dataInicio, $dataFim]);
    $resultHallFama = DB::select($queryHallFama, [$dataInicio, $dataFim]);

    $badges = [];

    if (!empty($resultBadge)) {
      $badgeTipo = $tipo === 'dia' ? 'campeao_dia' : ($tipo === 'semana' ? 'estrela_semana' : 'rei_mes');
      $badges[] = [
        'tipo' => $badgeTipo,
        'nome' => $resultBadge[0]->usuario_nome ?? 'N/A',
        'valor' => (float) ($resultBadge[0]->total_valor ?? 0),
      ];
    }

    $hallFama = array_map(function ($row) {
      return [
        'usuario_id' => $row->usuario_id,
        'usuario_nome' => $row->usuario_nome ?? 'Sem nome',
        'usuario_codigo' => $row->usuario_codigo ?? 'Sem código',
        'total_valor' => (float) ($row->total_valor ?? 0),
        'total_comandas' => (int) ($row->total_comandas ?? 0),
        'ticket_medio' => (float) ($row->ticket_medio ?? 0),
      ];
    }, $resultHallFama);

    return [
      'badges' => $badges,
      'hall_fama' => $hallFama,
    ];
  }
}
