<?php

declare(strict_types=1);

namespace App\Domain\Realtime\Port;

/**
 * Suscriptor conectado a un canal de tiempo real.
 *
 * Representa una conexión WebSocket abierta: recibe los eventos que el
 * canal difunde (véase D11).
 */
interface RealtimeSubscriber
{
    /**
     * Entrega el evento al suscriptor.
     *
     * @param  array<string, mixed>  $payload
     */
    public function deliver(string $event, array $payload): void;
}
