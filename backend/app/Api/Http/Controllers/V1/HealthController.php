<?php

declare(strict_types=1);

namespace App\Api\Http\Controllers\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Estado del servicio y de sus dependencias.
 *
 * La respuesta es pública (no exige `auth:api`) y no expone detalle técnico de
 * los fallos de conexión: sólo indica qué dependencia no responde.
 */
class HealthController
{
    /**
     * Estado de la aplicación.
     */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
        ];

        if ($this->usesRedis()) {
            $checks['redis'] = $this->checkRedis();
        }

        $healthy = ! in_array('ko', array_column($checks, 'status'), true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'service' => config('app.name'),
            'environment' => app()->environment(),
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    /**
     * Comprueba el acceso a la base de datos.
     *
     * @return array{status: string, driver: string, message?: string}
     */
    private function checkDatabase(): array
    {
        $driver = (string) config('database.default');

        try {
            DB::connection()->getPdo();

            return ['status' => 'ok', 'driver' => $driver];
        } catch (Throwable) {
            return ['status' => 'ko', 'driver' => $driver, 'message' => 'Sin conexión a la base de datos'];
        }
    }

    /**
     * Comprueba el acceso a Redis.
     *
     * @return array{status: string, message?: string}
     */
    private function checkRedis(): array
    {
        try {
            Redis::connection()->ping();

            return ['status' => 'ok'];
        } catch (Throwable) {
            return ['status' => 'ko', 'message' => 'Sin conexión a Redis'];
        }
    }

    /**
     * Indica si alguna dependencia configurada requiere Redis.
     */
    private function usesRedis(): bool
    {
        return in_array('redis', [
            (string) config('cache.default'),
            (string) config('queue.default'),
            (string) config('broadcasting.default'),
        ], true);
    }
}
