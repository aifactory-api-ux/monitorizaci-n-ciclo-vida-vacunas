<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Canales de difusión
|--------------------------------------------------------------------------
|
| El canal `batch-monitoring` es privado: sólo los usuarios autenticados con
| un token Bearer válido pueden suscribirse (véase D11).
|
*/

Broadcast::channel('batch-monitoring', function ($user): bool {
    return $user !== null;
}, ['guards' => ['api']]);
