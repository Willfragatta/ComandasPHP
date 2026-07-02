<?php

return [
    'pasta_rede' => env('PASTA_REDE', 'C:\\WRPDV\\serverun\\Exp\\pv'),
    'cliente_padrao' => env('CLIENTE_PADRAO', '113727'),
    'unidade_padrao' => env('UNIDADE_PADRAO', '001'),
    'departamentos' => array_filter(array_map('trim', explode(',', env('DEPARTAMENTOS', '027,029')))),
    'monitor_intervalo' => (int) env('MONITOR_INTERVALO', 5),
];
