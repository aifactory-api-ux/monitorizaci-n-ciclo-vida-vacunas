<?php

declare(strict_types=1);

namespace App\Domain\Realtime\ValueObject;

/**
 * Informe de capacidad de un canal de tiempo real.
 *
 * Recoge cuántos suscriptores estaban conectados, cuántos recibieron la
 * actualización, cuántas entregas fallaron y cuál fue la latencia máxima de
 * entrega. El informe acredita que el canal sostiene miles de conexiones
 * simultáneas sin degradación perceptible (véase NFR009).
 */
final class RealtimeCapacityReport
{
    public function __construct(
        private readonly int $subscribers,
        private readonly int $delivered,
        private readonly int $failures,
        private readonly float $maxLatencySeconds,
    ) {}

    /**
     * Suscriptores conectados al canal.
     */
    public function subscribers(): int
    {
        return $this->subscribers;
    }

    /**
     * Suscriptores que recibieron la actualización.
     */
    public function delivered(): int
    {
        return $this->delivered;
    }

    /**
     * Entregas que han fallado.
     */
    public function failures(): int
    {
        return $this->failures;
    }

    /**
     * Latencia máxima de entrega, en segundos.
     */
    public function maxLatencySeconds(): float
    {
        return $this->maxLatencySeconds;
    }

    /**
     * Indica si el canal entregó la actualización a todos sus suscriptores
     * sin errores.
     */
    public function hasNoDegradation(): bool
    {
        return $this->failures === 0 && $this->delivered === $this->subscribers;
    }

    /**
     * Indica si la latencia máxima está dentro del límite indicado.
     */
    public function isWithinLimit(float $limitSeconds): bool
    {
        return $this->maxLatencySeconds < $limitSeconds;
    }

    /**
     * Representación serializable del informe.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'subscribers' => $this->subscribers,
            'delivered' => $this->delivered,
            'failures' => $this->failures,
            'max_latency_seconds' => $this->maxLatencySeconds,
            'without_degradation' => $this->hasNoDegradation(),
        ];
    }
}
