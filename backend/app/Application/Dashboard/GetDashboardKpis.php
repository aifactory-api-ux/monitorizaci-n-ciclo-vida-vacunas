<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

use App\Domain\Dashboard\Service\CycleTimeCalculator;
use App\Domain\Dashboard\ValueObject\CycleTimeReport;
use App\Domain\VaccineBatch\Repository\PhaseTransitionRepository;
use App\Domain\VaccineBatch\Repository\VaccineBatchRepository;

/**
 * Caso de uso de consulta de los indicadores del panel.
 *
 * Reúne los lotes registrados y sus transiciones de fase para calcular los
 * tiempos de ciclo por fase y el desglose de estados (véase D21).
 */
final class GetDashboardKpis
{
    public function __construct(
        private readonly VaccineBatchRepository $batches,
        private readonly PhaseTransitionRepository $transitions,
        private readonly CycleTimeCalculator $calculator,
    ) {}

    /**
     * Indicadores de tiempos de ciclo y de desglose de estados.
     */
    public function execute(): CycleTimeReport
    {
        return $this->calculator->calculate(
            $this->batches->findAll(),
            $this->transitions->findAll(),
        );
    }
}
