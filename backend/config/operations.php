<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Configuración de la verificación operativa
|--------------------------------------------------------------------------
|
| Recoge los umbrales no funcionales exigidos por el enunciado, el canal de
| tiempo real que se mide y las etapas del análisis de seguridad que bloquean
| la promoción del código.
|
| Los umbrales se parametrizan por entorno para que cada despliegue declare
| los valores que ha verificado, sin que el código tenga un valor de reserva
| distinto del exigido.
|
| El comando de la etapa de seguridad no tiene valor por defecto: una etapa
| sin comando configurado no se puede ejecutar y, por tanto, bloquea la
| promoción. El despliegue declara el comando en `SECURITY_SCAN_COMMAND`.
|
*/

return [
    /*
     | Umbrales no funcionales verificados por el comando `ops:thresholds`.
     */
    'thresholds' => [
        'dashboard_seconds' => (float) env('OPERATIONS_DASHBOARD_THRESHOLD_SECONDS', 3.0),
        'search_p95_seconds' => (float) env('OPERATIONS_SEARCH_P95_THRESHOLD_SECONDS', 2.0),
        'event_latency_seconds' => (float) env('OPERATIONS_EVENT_LATENCY_THRESHOLD_SECONDS', 1.0),
        'protected_status_code' => (int) env('OPERATIONS_PROTECTED_STATUS_CODE', 401),
        'search_samples' => (int) env('OPERATIONS_SEARCH_SAMPLES', 20),
    ],

    /*
     | Canal de tiempo real y número de conexiones simultáneas con las que se
     | verifica la capacidad de difusión (véase NFR009).
     */
    'realtime' => [
        'channel' => env('OPERATIONS_REALTIME_CHANNEL', 'batch-monitoring'),
        'subscribers' => (int) env('OPERATIONS_REALTIME_SUBSCRIBERS', 2000),
    ],

    /*
     | Perímetro de exposición de la API dentro del despliegue privado (véase
     | NFR012). La ruta indicada se sondea sin credencial para comprobar que
     | responde 401.
     */
    'perimeter' => [
        'protected_uri' => env('OPERATIONS_PROTECTED_URI', 'api/v1/batches'),
    ],

    /*
     | Etapas del análisis de seguridad que bloquean la promoción del código
     | (véase D20 y NFR011).
     */
    'security' => [
        'stages' => [
            [
                'stage' => 'security-scan',
                'command' => env('SECURITY_SCAN_COMMAND', ''),
                'timeout_seconds' => (int) env('SECURITY_SCAN_TIMEOUT_SECONDS', 300),
            ],
        ],
    ],
];
