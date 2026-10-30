<?php

declare(strict_types=1);

namespace App\Domain\Realtime\Subscriber;

use App\Domain\Realtime\Port\RealtimeSubscriber;

/**
 * Panel conectado al canal de tiempo real.
 *
 * Conserva los eventos que recibe, de modo que la entrega a las conexiones
 * simultáneas sea verificable (véase NFR009).
 */
final class ConnectedClient implements RealtimeSubscriber
{
    /**
     * Eventos recibidos por el panel.
     *
     * @var list<array{event: string, payload: array<string, mixed>}>
     */
    private array $events = [];

    public function deliver(string $event, array $payload): void
    {
        $this->events[] = ['event' => $event, 'payload' => $payload];
    }

    /**
     * Número de eventos recibidos.
     */
    public function deliveries(): int
    {
        return count($this->events);
    }

    /**
     * Eventos recibidos por el panel.
     *
     * @return list<array{event: string, payload: array<string, mixed>}>
     */
    public function events(): array
    {
        return $this->events;
    }
}
