<?php

declare(strict_types=1);

namespace App\Application\Realtime;

use App\Domain\Realtime\Port\RealtimeSubscriberRegistry;
use App\Domain\Realtime\Subscriber\ConnectedClient;
use App\Domain\Realtime\ValueObject\RealtimeCapacityReport;

/**
 * Caso de uso de medición de la capacidad del canal de tiempo real.
 *
 * Conecta el número indicado de paneles al canal y devuelve el informe de
 * capacidad y de entrega, que acredita que el canal sostiene miles de
 * conexiones simultáneas sin degradación perceptible (véase NFR009).
 */
final class MeasureRealtimeCapacity
{
    public function __construct(private readonly RealtimeSubscriberRegistry $registry) {}

    /**
     * Conecta el número indicado de paneles al canal.
     */
    public function connect(string $channel, int $subscribers): int
    {
        for ($connection = 0; $connection < $subscribers; $connection++) {
            $this->registry->subscribe($channel, new ConnectedClient);
        }

        return $this->registry->subscriberCount($channel);
    }

    /**
     * Informe de capacidad y de entrega del canal.
     */
    public function report(string $channel): RealtimeCapacityReport
    {
        return $this->registry->report($channel);
    }
}
