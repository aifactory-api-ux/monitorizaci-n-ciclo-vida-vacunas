<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Domain\Realtime\Port\RealtimeSubscriber;
use RuntimeException;

/**
 * Doble de un suscriptor que falla al recibir la actualización.
 *
 * Reproduce una conexión que se pierde durante la entrega, de modo que el
 * registro de suscriptores pueda contabilizar los fallos del canal.
 */
final class FailingRealtimeSubscriber implements RealtimeSubscriber
{
    public function deliver(string $event, array $payload): void
    {
        throw new RuntimeException('La conexión con el panel se ha perdido durante la entrega.');
    }
}
