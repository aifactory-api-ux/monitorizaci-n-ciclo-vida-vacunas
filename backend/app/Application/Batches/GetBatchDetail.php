<?php

declare(strict_types=1);

namespace App\Application\Batches;

use App\Application\Authorization\BatchVisibilityPolicy;
use App\Application\Lims\EvaluateGatingRelease;
use App\Domain\Lims\Repository\LimsResultRepository;
use App\Domain\Shared\Exception\ResourceNotFoundException;
use App\Domain\User\ValueObject\UserRole;
use App\Domain\VaccineBatch\Repository\VaccineBatchRepository;

/**
 * Caso de uso de consulta del detalle de un lote.
 *
 * Un lote fuera del alcance de visibilidad del rol no existe para el
 * solicitante, por lo que la respuesta es 404 y no 403 (véase TC027).
 */
final class GetBatchDetail
{
    public function __construct(
        private readonly VaccineBatchRepository $batches,
        private readonly LimsResultRepository $limsResults,
        private readonly EvaluateGatingRelease $evaluateGating,
        private readonly BatchVisibilityPolicy $visibility,
    ) {}

    /**
     * Detalle del lote indicado.
     *
     * @throws ResourceNotFoundException Si el lote no existe o no es visible por el rol.
     */
    public function execute(string $batchId, UserRole $role): BatchDetail
    {
        $batch = $this->batches->findById($batchId);

        if ($batch === null || ! $this->visibility->allows($role, $batch->phase())) {
            throw ResourceNotFoundException::batch($batchId);
        }

        return new BatchDetail(
            $batch,
            $this->limsResults->findByBatchId($batchId),
            $this->evaluateGating->execute($batchId),
        );
    }
}
