<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\ValueObject;

/**
 * Indicadores de tiempos de ciclo y desglose de estados del panel.
 *
 * Agrupa los indicadores de la planta: el tiempo de ciclo de cada fase del
 * ciclo de vida del lote y el peso de cada estado sobre el total de lotes
 * registrados (véase D21).
 */
final class CycleTimeReport
{
    /**
     * @param  list<PhaseCycleTime>  $byPhase
     * @param  list<StatusBreakdown>  $statusBreakdown
     */
    public function __construct(
        private readonly array $byPhase,
        private readonly array $statusBreakdown,
        private readonly int $totalBatches,
    ) {}

    /**
     * Tiempo de ciclo de cada fase del ciclo de vida.
     *
     * @return list<PhaseCycleTime>
     */
    public function byPhase(): array
    {
        return $this->byPhase;
    }

    /**
     * Peso de cada estado de lote sobre el total.
     *
     * @return list<StatusBreakdown>
     */
    public function statusBreakdown(): array
    {
        return $this->statusBreakdown;
    }

    /**
     * Total de lotes registrados.
     */
    public function totalBatches(): int
    {
        return $this->totalBatches;
    }
}
