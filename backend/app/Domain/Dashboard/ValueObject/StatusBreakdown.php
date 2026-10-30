<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\ValueObject;

/**
 * Peso de un estado de lote sobre el total de lotes registrados.
 */
final class StatusBreakdown
{
    public function __construct(
        private readonly string $status,
        private readonly int $count,
        private readonly float $percentage,
    ) {}

    /**
     * Literal del estado del lote.
     */
    public function status(): string
    {
        return $this->status;
    }

    /**
     * Número de lotes en el estado.
     */
    public function count(): int
    {
        return $this->count;
    }

    /**
     * Porcentaje de lotes en el estado sobre el total.
     */
    public function percentage(): float
    {
        return $this->percentage;
    }
}
