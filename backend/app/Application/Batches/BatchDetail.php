<?php

declare(strict_types=1);

namespace App\Application\Batches;

use App\Application\Lims\GatingStatus;
use App\Domain\Lims\Entity\LimsResult;
use App\Domain\VaccineBatch\Entity\VaccineBatch;

/**
 * Detalle de un lote: su estado, sus resultados de LIMS y su gating.
 */
final class BatchDetail
{
    /**
     * @param  list<LimsResult>  $limsResults
     */
    public function __construct(
        private readonly VaccineBatch $batch,
        private readonly array $limsResults,
        private readonly GatingStatus $gating,
    ) {}

    /**
     * Lote consultado.
     */
    public function batch(): VaccineBatch
    {
        return $this->batch;
    }

    /**
     * Resultados de LIMS registrados para el lote.
     *
     * @return list<LimsResult>
     */
    public function limsResults(): array
    {
        return $this->limsResults;
    }

    /**
     * Estado del gating microbiológico del lote.
     */
    public function gating(): GatingStatus
    {
        return $this->gating;
    }
}
