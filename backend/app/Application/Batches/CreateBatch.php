<?php

declare(strict_types=1);

namespace App\Application\Batches;

use App\Domain\Audit\Service\StructuredAuditLogger;
use App\Domain\Search\Port\BatchSearchCache;
use App\Domain\Shared\Event\DomainEventRecorder;
use App\Domain\Shared\Exception\ConfigurationException;
use App\Domain\Shared\Exception\DomainException;
use App\Domain\VaccineBatch\Entity\VaccineBatch;
use App\Domain\VaccineBatch\Event\BatchCreated;
use App\Domain\VaccineBatch\Repository\CampusRepository;
use App\Domain\VaccineBatch\Repository\VaccineBatchRepository;
use App\Domain\VaccineBatch\Repository\VaccineReferenceRepository;
use App\Domain\VaccineBatch\Service\PhaseTransitionPolicy;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;
use DateTimeImmutable;

/**
 * Caso de uso de alta de lote de vacuna.
 *
 * El lote nace en la fase de cultivo, con el estado "En curso" y en el
 * Campus 1 (véase TC015).
 */
final class CreateBatch
{
    /**
     * Tipo de evento registrado en el log estructurado.
     */
    public const EVENT_TYPE = 'batch.created';

    public function __construct(
        private readonly VaccineBatchRepository $batches,
        private readonly VaccineReferenceRepository $references,
        private readonly CampusRepository $campuses,
        private readonly PhaseTransitionPolicy $policy,
        private readonly StructuredAuditLogger $auditLogger,
        private readonly BatchSearchCache $searchCache,
        private readonly DomainEventRecorder $events,
    ) {}

    /**
     * Crea el lote y lo registra en el log de trazabilidad.
     *
     * @throws DomainException Si la referencia de vacuna no existe en el catálogo.
     * @throws ConfigurationException Si el campus de cultivo no está dado de alta.
     */
    public function execute(string $referenceId, ?string $actorId = null, ?DateTimeImmutable $now = null): VaccineBatch
    {
        if ($this->references->findById($referenceId) === null) {
            throw DomainException::unknownReference($referenceId);
        }

        $campusName = $this->policy->campusNameFor(BatchPhase::from(BatchPhase::CULTURE));
        $campus = $this->campuses->findByName($campusName);

        if ($campus === null) {
            throw ConfigurationException::missingCampus($campusName);
        }

        $timestamp = $now ?? new DateTimeImmutable;
        $batch = VaccineBatch::create($referenceId, $campus->id(), $timestamp);

        $this->batches->save($batch);

        $this->searchCache->invalidate();

        $this->auditLogger->record(
            self::EVENT_TYPE,
            [
                'phase' => $batch->phase()->value(),
                'status' => $batch->status()->value(),
                'current_campus_id' => $batch->currentCampusId(),
                'reference_id' => $batch->referenceId(),
            ],
            $batch->id(),
            $actorId,
            $timestamp,
        );

        // El alta se difunde en el canal de monitorización para que los
        // paneles renueven el listado sin esperar a una consulta manual
        // (véase D11).
        $this->events->record(BatchCreated::fromBatch($batch));

        return $batch;
    }
}
