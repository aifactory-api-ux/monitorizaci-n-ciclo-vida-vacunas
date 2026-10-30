<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * El grupo `api` protege las rutas de datos con `auth:api` (véase D9): sin un
 * token Bearer válido la respuesta es 401 y sólo las rutas públicas, como el
 * sondeo de disponibilidad, quedan abiertas.
 */
class ApiRouteProtectionTest extends TestCase
{
    public function test_data_routes_reject_requests_without_a_bearer_token(): void
    {
        $this->registerProtectedRoute();

        $this->getJson('/api/v1/probe')->assertUnauthorized();
    }

    public function test_data_routes_reject_requests_with_an_invalid_bearer_token(): void
    {
        $this->registerProtectedRoute();

        $this->getJson('/api/v1/probe', [
            'Authorization' => 'Bearer token-invalido',
        ])->assertUnauthorized();
    }

    public function test_the_health_route_remains_public(): void
    {
        $this->getJson('/api/v1/health')->assertOk();
    }

    /**
     * Registra una ruta de datos con el grupo `api`, igual que las que se
     * registrarán para los lotes, el histórico y los reportes.
     */
    private function registerProtectedRoute(): void
    {
        Route::middleware('api')->get('/api/v1/probe', fn (): array => ['ok' => true]);
    }
}
