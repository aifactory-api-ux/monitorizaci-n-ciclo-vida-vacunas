<?php

declare(strict_types=1);

namespace App\Domain\Report\Service;

use App\Domain\Alert\Repository\AlertRepository;
use App\Domain\Audit\Repository\AuditTrailRepository;
use App\Domain\Lims\Repository\LimsResultRepository;
use App\Domain\Report\Entity\TraceabilityReport;
use App\Domain\Shared\Exception\ResourceNotFoundException;
use App\Domain\VaccineBatch\Repository\PhaseTransitionRepository;
use App\Domain\VaccineBatch\Repository\VaccineBatchRepository;
use App\Domain\VaccineBatch\Repository\VaccineReferenceRepository;
use App\Domain\VaccineBatch\Service\CampusSequenceBuilder;

/**
 * Composición del modelo de trazabilidad de un lote de vacuna.
 *
 * Reúne las piezas dispersas del ciclo de vida del lote en un único modelo
 * que los exportadores serializan sin volver a consultar los repositorios
 * (D15).
 */
final class TraceabilityReportBuilder
{
    public function __construct(
        private readonly VaccineBatchRepository $batches,
        private readonly PhaseTransitionRepository $transitions,
        private readonly LimsResultRepository $limsResults,
        private readonly AlertRepository $alerts,
        private readonly AuditTrailRepository $auditTrail,
        private readonly VaccineReferenceRepository $references,
        private readonly CampusSequenceBuilder $campusSequence,
    ) {}

    /**
     * Compone el modelo de trazabilidad del lote indicado.
     *
     * @throws ResourceNotFoundException Si el lote no existe.
     */
    public function build(string $batchId): TraceabilityReport
    {
        $batch = $this->batches->findById($batchId);

        if ($batch === null) {
            throw ResourceNotFoundException::batch($batchId);
        }

        $transitions = $this->transitions->findByBatchId($batchId);

        return new TraceabilityReport(
            $batch,
            $this->references->findById($batch->referenceId()),
            $transitions,
            $this->limsResults->findByBatchId($batchId),
            $this->alerts->findByBatchId($batchId),
            $this->auditTrail->findByBatchId($batchId),
            $this->campusSequence->build($batch, $transitions),
        );
    }
}
