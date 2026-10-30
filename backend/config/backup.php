<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Directorio de los artefactos de respaldo
    |--------------------------------------------------------------------------
    |
    | Cada artefacto de respaldo es un directorio con el manifiesto, el
    | volcado de los datos críticos y la instantánea del almacén de claves.
    | El directorio debe residir en un volumen persistente y no público.
    |
    */

    'path' => env('BACKUP_PATH', storage_path('app/backups')),

    /*
    |--------------------------------------------------------------------------
    | Conexión de base de datos de la que se extraen los datos críticos
    |--------------------------------------------------------------------------
    |
    | Cuando no se declara ninguna conexión se utiliza la conexión por
    | defecto de la aplicación, que en el despliegue es MySQL 8.0.
    |
    */

    'connection' => env('BACKUP_DATABASE_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Tablas críticas y su orden de dependencia
    |--------------------------------------------------------------------------
    |
    | El orden declara primero las tablas de las que dependen las demás, de
    | modo que la restauración incorpora las filas sin violar ninguna
    | clave ajena.
    |
    */

    'tables' => [
        'VACCINE_REFERENCE',
        'CAMPUS',
        'APP_USER',
        'VACCINE_BATCH',
        'PHASE_TRANSITION',
        'LIMS_RESULT',
        'ALERT',
        'LOG_ENTRY',
    ],

    /*
    |--------------------------------------------------------------------------
    | Instantánea del almacén de claves
    |--------------------------------------------------------------------------
    |
    | La conexión declarada se abre sin prefijo de clave, de modo que la
    | instantánea conserva los nombres reales de las claves y la
    | restauración repone exactamente el mismo estado del caché.
    |
    */

    'cache' => [
        'connection' => env('BACKUP_REDIS_CONNECTION', 'backup'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retención de los artefactos de respaldo
    |--------------------------------------------------------------------------
    |
    | Periodo en días que se conservan los artefactos de respaldo. El
    | borrado de los artefactos expirados se ejecuta fuera de la
    | aplicación, desde el planificador del despliegue.
    |
    */

    'retention' => [
        'days' => (int) env('BACKUP_RETENTION_DAYS', 30),
    ],

];
