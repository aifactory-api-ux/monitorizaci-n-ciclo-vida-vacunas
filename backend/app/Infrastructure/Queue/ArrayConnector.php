<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue;

use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\Connectors\ConnectorInterface;

/**
 * Conector de la cola en memoria.
 *
 * Resuelve el driver `array` de la aplicación: la cola vive en el proceso
 * PHP, sin MySQL ni Redis, y por tanto es apta para el entorno de pruebas.
 */
class ArrayConnector implements ConnectorInterface
{
    /**
     * Establece la conexión de cola en memoria.
     *
     * @param  array<string, mixed>  $config
     */
    public function connect(array $config): Queue
    {
        return new ArrayQueue($config['after_commit'] ?? false);
    }
}
