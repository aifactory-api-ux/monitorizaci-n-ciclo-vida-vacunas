<?php

declare(strict_types=1);

namespace App\Application\Batches;

use App\Domain\Alert\Repository\AlertRepository;
use App\Domain\Audit\Repository\AuditTrailRepository;
use App\Domain\Lims\Repository\LimsResultRepository;
use App\Domain\Shared\Exception\ResourceNotFoundException;
use App\Domain\VaccineBatch\Repository\PhaseTransitionRepository;
use App\Domain\VaccineBatch\Repository\VaccineBatchRepository;
use App\Domain\VaccineBatch\Service\CampusSequenceBuilder;

/**
 * Caso de uso de consulta de la trazabilidad de un lote.
 *
 * La trazabilidad completa el recorrido del lote entre campus con sus
 * transiciones de fase ordenadas cronológicamente, sus resultados de LIMS,
 * sus alertas y su historial auditable (TC003).
 */
final class GetBatchTraceability
{
    public function __construct(
        private readonly VaccineBatchRepository $batches,
        private readonly PhaseTransitionRepository $transitions,
        private readonly LimsResultRepository $limsResults,
        private readonly AlertRepository $alerts,
        private readonly AuditTrailRepository $auditTrail,
        private readonly CampusSequenceBuilder $campusSequence,
    ) {}

    /**
     * Trazabilidad del lote indicado.
     *
     * @throws ResourceNotFoundException Si el lote no existe.
     */
    public function execute(string $batchId): BatchTraceability
    {
        $batch = $this->batches->findById($batchId);

        if ($batch === null) {
            throw ResourceNotFoundException::batch($batchId);
        }

        $transitions = $this->transitions->findByBatchId($batchId);

        return new BatchTraceability(
            $batch,
            $transitions,
            $this->limsResults->findByBatchId($batchId),
            $this->alerts->findByBatchId($batchId),
            $this->auditTrail->findByBatchId($batchId),
            $this->campusSequence->build($batch, $transitions),
        );
    }
}
