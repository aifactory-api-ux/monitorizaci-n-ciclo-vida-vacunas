<?php

declare(strict_types=1);

namespace App\Infrastructure\Realtime;

use App\Domain\Realtime\Port\RealtimeSubscriber;
use App\Domain\Realtime\Port\RealtimeSubscriberRegistry;
use App\Domain\Realtime\ValueObject\RealtimeCapacityReport;
use Throwable;

/**
 * Registro de suscriptores del canal de tiempo real en memoria.
 *
 * Simula las conexiones WebSocket activas del canal: cada suscriptor
 * registrado recibe las difusiones del canal y el registro mide la latencia
 * de la entrega y los fallos, de modo que la capacidad del canal sea
 * verificable sin levantar el servidor de WebSockets (véase D11 y NFR009).
 */
final class ArraySubscriberRegistry implements RealtimeSubscriberRegistry
{
    /**
     * Suscriptores registrados, indexados por canal.
     *
     * @var array<string, list<RealtimeSubscriber>>
     */
    private array $subscribers = [];

    /**
     * Suscriptores que recibieron la última difusión, indexados por canal.
     *
     * @var array<string, int>
     */
    private array $delivered = [];

    /**
     * Entregas fallidas, indexadas por canal.
     *
     * @var array<string, int>
     */
    private array $failures = [];

    /**
     * Latencia máxima de entrega, indexada por canal.
     *
     * @var array<string, float>
     */
    private array $maxLatency = [];

    public function subscribe(string $channel, RealtimeSubscriber $subscriber): void
    {
        $this->subscribers[$channel][] = $subscriber;
    }

    public function subscriberCount(string $channel): int
    {
        return count($this->subscribers[$channel] ?? []);
    }

    public function deliver(string $channel, string $event, array $payload): void
    {
        $subscribers = $this->subscribers[$channel] ?? [];

        if ($subscribers === []) {
            return;
        }

        $startedAt = microtime(true);
        $delivered = 0;
        $failures = 0;

        foreach ($subscribers as $subscriber) {
            try {
                $subscriber->deliver($event, $payload);
                $delivered++;
            } catch (Throwable) {
                $failures++;
            }
        }

        $latency = microtime(true) - $startedAt;

        $this->delivered[$channel] = $delivered;
        $this->failures[$channel] = ($this->failures[$channel] ?? 0) + $failures;
        $this->maxLatency[$channel] = max($this->maxLatency[$channel] ?? 0.0, $latency);
    }

    public function report(string $channel): RealtimeCapacityReport
    {
        return new RealtimeCapacityReport(
            $this->subscriberCount($channel),
            $this->delivered[$channel] ?? 0,
            $this->failures[$channel] ?? 0,
            $this->maxLatency[$channel] ?? 0.0,
        );
    }
}
