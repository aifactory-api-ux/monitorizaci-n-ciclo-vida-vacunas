<?php

declare(strict_types=1);

namespace App\Domain\Realtime\Port;

use App\Domain\Realtime\ValueObject\RealtimeCapacityReport;

/**
 * Registro de suscriptores de un canal de tiempo real.
 *
 * Permite contabilizar las conexiones activas de un canal y repartir las
 * actualizaciones entre ellas, de modo que la capacidad del canal y la
 * latencia de entrega sean medibles (véase D11 y NFR009).
 */
interface RealtimeSubscriberRegistry
{
    /**
     * Registra un suscriptor en el canal indicado.
     */
    public function subscribe(string $channel, RealtimeSubscriber $subscriber): void;

    /**
     * Número de suscriptores conectados al canal.
     */
    public function subscriberCount(string $channel): int;

    /**
     * Entrega el evento a los suscriptores del canal.
     *
     * @param  array<string, mixed>  $payload
     */
    public function deliver(string $channel, string $event, array $payload): void;

    /**
     * Informe de capacidad y de entrega del canal.
     */
    public function report(string $channel): RealtimeCapacityReport;
}
