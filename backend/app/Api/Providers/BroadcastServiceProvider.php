<?php

declare(strict_types=1);

namespace App\Api\Providers;

use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * Proveedor de servicios de broadcasting.
 *
 * Comprueba al arrancar que la conexión de broadcasting configurada existe y
 * que dispone de las credenciales obligatorias. El fallo es explícito y no
 * incluye ningún valor de secreto (véase D16).
 */
class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Conexiones que requieren credenciales propias.
     *
     * @var array<string, array<string, string>>
     */
    private const REQUIRED_CREDENTIALS = [
        'reverb' => [
            'key' => 'REVERB_APP_KEY',
            'secret' => 'REVERB_APP_SECRET',
            'app_id' => 'REVERB_APP_ID',
        ],
        'pusher' => [
            'key' => 'PUSHER_APP_KEY',
            'secret' => 'PUSHER_APP_SECRET',
            'app_id' => 'PUSHER_APP_ID',
        ],
    ];

    public function boot(): void
    {
        $this->assertBroadcastConnectionIsConfigured();
    }

    /**
     * Verifica la conexión de broadcasting por defecto.
     *
     * @throws RuntimeException
     */
    private function assertBroadcastConnectionIsConfigured(): void
    {
        $connection = config('broadcasting.default');

        if (! is_string($connection) || $connection === '') {
            throw new RuntimeException(
                'No hay ninguna conexión de broadcasting por defecto; defina BROADCAST_CONNECTION.'
            );
        }

        $connections = config('broadcasting.connections', []);

        if (! array_key_exists($connection, $connections)) {
            throw new RuntimeException(sprintf(
                'La conexión de broadcasting "%s" no está definida en config/broadcasting.php.',
                $connection
            ));
        }

        $driver = $connections[$connection]['driver'] ?? null;
        $required = self::REQUIRED_CREDENTIALS[$driver] ?? [];

        foreach ($required as $key => $variable) {
            if (blank($connections[$connection][$key] ?? null)) {
                throw new RuntimeException(sprintf(
                    'Falta la variable %s, obligatoria para la conexión de broadcasting "%s".',
                    $variable,
                    $connection
                ));
            }
        }
    }
}
