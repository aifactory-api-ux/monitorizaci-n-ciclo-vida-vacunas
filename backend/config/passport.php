<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Passport Guard
    |--------------------------------------------------------------------------
    |
    | Guardia que Passport utiliza en sus propias rutas web. El panel obtiene
    | el token JWT en `POST /api/v1/auth/token`, que no depende de esta ruta
    | (véase D9).
    |
    */

    'guard' => env('PASSPORT_GUARD', 'web'),

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware aplicado a las rutas propias de Passport. El contrato de la
    | API aplica su propia autenticación con `auth:api` (véase D9).
    |
    */

    'middleware' => [],

    /*
    |--------------------------------------------------------------------------
    | Encryption Keys
    |--------------------------------------------------------------------------
    |
    | Claves de firma de los tokens JWT. El despliegue las inyecta el gestor
    | de secretos; en local las genera `php artisan passport:keys` (véase D16).
    |
    */

    'private_key' => env('PASSPORT_PRIVATE_KEY'),

    'public_key' => env('PASSPORT_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Passport Database Connection
    |--------------------------------------------------------------------------
    |
    | Conexión de base de datos utilizada por los modelos de Passport. Por
    | defecto se emplea la conexión principal de la aplicación.
    |
    */

    'connection' => env('PASSPORT_CONNECTION'),

];
