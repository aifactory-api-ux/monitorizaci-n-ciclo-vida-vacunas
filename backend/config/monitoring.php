<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Configuración de la monitorización de lotes
|--------------------------------------------------------------------------
|
| La monitorización de la planta publica lecturas de desviación térmica y de
| anomalía de formulación Sphereon. Los umbrales se parametrizan por entorno
| mientras se concuerda el valor definitivo con el área de calidad, de modo que
| el detector trabaja siempre con el umbral declarado en esta configuración.
|
| Los canales de notificación se habilitan de forma explícita: un canal
 | habilitado sin destinatarios no puede arrancar, igual que una credencial
 | ausente. Los destinatarios se declaran como una lista separada por comas.
|
| La monitorización publica sus lecturas en un fichero JSON con este formato:
|
|     [
|         {
|             "batch_id": "uuid del lote",
|             "thermal_deviation": 4.2,
|             "sphereon_anomaly": 6.5,
|             "detected_at": "2026-01-05T09:30:00+00:00"
|         }
|     ]
|
| La evaluación de las lecturas es automática: el comando `alerts:monitor`
| recorre el fichero, evalúa cada lectura con los detectores y genera las
| alertas que correspondan, sin que ninguna persona tenga que intervenir.
|
*/

return [
    /*
     | Umbrales de la desviación térmica del lote, en grados centígrados.
     */
    'thermal' => [
        'deviation_threshold' => (float) env('MONITORING_THERMAL_DEVIATION_THRESHOLD', 2.0),
    ],

    /*
     | Umbrales de la anomalía de formulación Sphereon, en puntos porcentuales.
     */
    'sphereon' => [
        'anomaly_threshold' => (float) env('MONITORING_SPHEREON_ANOMALY_THRESHOLD', 5.0),
    ],

    /*
     | Canales de notificación de las alertas de prioridad máxima.
     */
    'notification' => [
        'email' => [
            'enabled' => (bool) env('MONITORING_EMAIL_ENABLED', false),
            'recipients' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('MONITORING_EMAIL_RECIPIENTS', '')),
            ))),
        ],
        'sms' => [
            'enabled' => (bool) env('MONITORING_SMS_ENABLED', false),
            'recipients' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('MONITORING_SMS_RECIPIENTS', '')),
            ))),
        ],
    ],

    /*
     | Ruta del fichero de lecturas que publica la monitorización.
     */
    'feed' => [
        'path' => env('MONITORING_FEED_PATH'),
    ],
];
