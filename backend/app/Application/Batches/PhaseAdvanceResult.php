<?php

declare(strict_types=1);

namespace App\Application\Batches;

use App\Domain\VaccineBatch\Entity\PhaseTransition;
use App\Domain\VaccineBatch\Entity\VaccineBatch;

/**
 * Resultado de un avance de fase: el lote actualizado y su transición.
 */
final readonly class PhaseAdvanceResult
{
    public function __construct(
        private VaccineBatch $batch,
        private PhaseTransition $transition,
    ) {}

    /**
     * Lote tras el avance.
     */
    public function batch(): VaccineBatch
    {
        return $this->batch;
    }

    /**
     * Transición registrada.
     */
    public function transition(): PhaseTransition
    {
        return $this->transition;
    }
}
