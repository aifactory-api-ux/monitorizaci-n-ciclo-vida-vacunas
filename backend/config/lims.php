<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Integración con LIMS Labware
|--------------------------------------------------------------------------
|
| La integración con LIMS Labware es de sólo lectura (véase D7): el sistema
| consulta los resultados microbiológicos y nunca escribe en el sistema
| externo. La conexión y el token se inyectan por entorno y se consumen desde
| el gestor de secretos del despliegue; no hay valores por defecto inventados,
| de modo que una integración habilitada sin credenciales falla al arrancar.
|
| Contrato de la carga devuelta por LIMS Labware:
|
|     [
|         'batch_id'     => 'uuid del lote',
|         'reference_id' => 'uuid de la referencia de vacuna',
|         'results'      => [
|             [
|                 'test_type'   => 'Microbiología',
|                 'result'      => 'Aprobado',
|                 'approved_by' => 'identificador del responsable de la aprobación',
|                 'result_date' => '2026-01-05T09:30:00+00:00',
|                 'updated_at'  => '2026-01-05T09:30:00+00:00',
|             ],
|         ],
|     ]
|
*/

return [
    /*
     | La integración se habilita de forma explícita: mientras no lo esté, el
     | sistema no intenta leer nada de LIMS Labware.
     */
    'enabled' => (bool) env('LIMS_ENABLED', false),

    /*
     | URL base del servicio de LIMS Labware.
     */
    'base_url' => env('LIMS_BASE_URL'),

    /*
     | Token de acceso al servicio de LIMS Labware.
     */
    'api_token' => env('LIMS_API_TOKEN'),

    /*
     | Tiempo máximo de espera de cada lectura, en segundos.
     */
    'timeout' => (int) env('LIMS_TIMEOUT', 10),

    /*
     | Ruta de lectura de las cargas de resultados.
     */
    'results_path' => env('LIMS_RESULTS_PATH', '/api/v1/batches/results'),

    /*
     | Ruta de lectura de la aprobación microbiológica de un lote.
     */
    'microbiology_path' => env('LIMS_MICROBIOLOGY_PATH', '/api/v1/microbiology'),

    /*
     | Clave del objeto que agrupa las cargas en la respuesta de resultados.
     */
    'response_key' => env('LIMS_RESPONSE_KEY', 'batches'),
];
