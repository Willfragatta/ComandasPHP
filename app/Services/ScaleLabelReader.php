<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ScaleLabelReader
{
  public static function parsearCodigoBarras(string $codigoBarras): ?array
  {
    try {
      $codigoLimpo = preg_replace('/\D/', '', $codigoBarras);

      if (strlen($codigoLimpo) !== 13) {
        Log::error("Código de barras deve ter 13 dígitos, recebido: " . strlen($codigoLimpo));

        return null;
      }

      if ($codigoLimpo[0] !== '2') {
        Log::error("Código de barras deve começar com 2, recebido: {$codigoLimpo[0]}");

        return null;
      }

      $codigoComZeros = substr($codigoLimpo, 1, 4);
      $codigoProduto = (string) (int) $codigoComZeros;
      $valorCompleto = substr($codigoLimpo, 6, 6);
      $digitoVerificador = $codigoLimpo[12];
      $valorTotal = (int) $valorCompleto / 100;

      Log::info("Etiqueta analisada: {$codigoLimpo} -> Código: {$codigoProduto}, Valor: R$ {$valorTotal}");

      return [
        'codigoBarras' => $codigoProduto,
        'valorTotal' => $valorTotal,
        'digitoVerificador' => $digitoVerificador,
        'codigoCompleto' => $codigoLimpo,
      ];
    } catch (\Throwable $error) {
      Log::error('Erro ao analisar código de barras: ' . $error->getMessage());

      return null;
    }
  }

  public static function ehEtiquetaBalanca(string $codigoBarras): bool
  {
    $codigoLimpo = preg_replace('/\D/', '', $codigoBarras);

    return strlen($codigoLimpo) === 13 && $codigoLimpo[0] === '2';
  }

  public static function calcularQuantidade(float $valorTotal, float $precoUnitario): float
  {
    if ($precoUnitario <= 0) {
      return 0;
    }

    $quantidade = $valorTotal / $precoUnitario;

    return round($quantidade * 1000) / 1000;
  }
}
