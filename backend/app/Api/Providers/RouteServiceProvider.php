<?php

declare(strict_types=1);

namespace App\Api\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Proveedor de servicios de enrutado de la API.
 *
 * Define los limitadores de peticiones de la API. El límite se asigna al actor
 * autenticado cuando lo hay y, en caso contrario, a la dirección IP de
 * origen.
 */
class RouteServiceProvider extends ServiceProvider
{
    /**
     * Peticiones por minuto admitidas para un mismo actor.
     */
    public const API_LIMIT_PER_MINUTE = 120;

    public function boot(): void
    {
        $this->registerApiRateLimiter();
    }

    /**
     * Registra el limitador `api`, aplicado al grupo de rutas de `/api/v1`.
     */
    private function registerApiRateLimiter(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            $identifier = $request->user()?->getAuthIdentifier();

            return Limit::perMinute(self::API_LIMIT_PER_MINUTE)->by(
                $identifier === null ? 'ip:'.$request->ip() : 'user:'.$identifier
            );
        });
    }
}
