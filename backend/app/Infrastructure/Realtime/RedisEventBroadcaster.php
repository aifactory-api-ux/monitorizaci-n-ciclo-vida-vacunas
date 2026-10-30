<?php

declare(strict_types=1);

namespace App\Infrastructure\Realtime;

use App\Domain\Shared\Event\DomainEvent;
use App\Domain\Shared\Event\DomainEventRecorder;
use Illuminate\Support\Facades\Broadcast;

/**
 * Publicador de eventos de dominio en el canal privado de monitorización.
 *
 * Reverb es el servidor WebSocket y Redis actúa como backend de broadcasting,
 * de modo que todas las instancias del BFF publican y reciben los mismos
 * eventos (véase D11). La publicación es síncrona: el evento está disponible
 * en el canal en el mismo instante en que el caso de uso confirma la
 * operación, sin depender de un consumidor de cola.
 */
final class RedisEventBroadcaster implements DomainEventRecorder
{
    /**
     * Canal privado por el que se difunden los eventos de lote y de alerta.
     */
    public const CHANNEL = 'batch-monitoring';

    /**
     * Publica el evento en el canal privado de monitorización.
     */
    public function record(DomainEvent $event): void
    {
        Broadcast::private(self::CHANNEL)
            ->as($event->name())
            ->with($event->payload())
            ->sendNow();
    }
}
