<?php

declare(strict_types=1);

namespace App\Application\Batches;

use App\Domain\Audit\Service\StructuredAuditLogger;
use App\Domain\Search\Port\BatchSearchCache;
use App\Domain\Shared\Event\DomainEventRecorder;
use App\Domain\Shared\Exception\DomainException;
use App\Domain\Shared\Exception\ResourceNotFoundException;
use App\Domain\VaccineBatch\Entity\VaccineBatch;
use App\Domain\VaccineBatch\Event\LogisticsCycleClosed;
use App\Domain\VaccineBatch\Repository\VaccineBatchRepository;
use DateTimeImmutable;

/**
 * Caso de uso de cierre del ciclo logístico de un lote.
 *
 * El cierre deja el lote en el estado "Cerrado" y sólo se admite sobre un
 * lote con la recepción física registrada (véase TC017).
 */
final class CloseLogisticsCycle
{
    /**
     * Tipo de evento registrado en el log estructurado.
     */
    public const EVENT_TYPE = 'batch.logistics_cycle.closed';

    public function __construct(
        private readonly VaccineBatchRepository $batches,
        private readonly StructuredAuditLogger $auditLogger,
        private readonly BatchSearchCache $searchCache,
        private readonly DomainEventRecorder $events,
    ) {}

    /**
     * Cierra el ciclo logístico del lote.
     *
     * @throws ResourceNotFoundException Si el lote no existe.
     * @throws DomainException Si el lote no tiene la recepción física registrada.
     */
    public function execute(string $batchId, ?string $actorId = null, ?DateTimeImmutable $now = null): VaccineBatch
    {
        $batch = $this->batches->findById($batchId);

        if ($batch === null) {
            throw ResourceNotFoundException::batch($batchId);
        }

        $timestamp = $now ?? new DateTimeImmutable;
        $batch->closeLogisticsCycle($timestamp);

        $this->batches->save($batch);
        $this->searchCache->invalidate();

        $this->auditLogger->record(
            self::EVENT_TYPE,
            [
                'phase' => $batch->phase()->value(),
                'status' => $batch->status()->value(),
                'current_campus_id' => $batch->currentCampusId(),
            ],
            $batchId,
            $actorId,
            $timestamp,
        );

        // El cierre se difunde en el canal de monitorización con el estado
        // resultante del lote (véase D11).
        $this->events->record(LogisticsCycleClosed::fromBatch($batch));

        return $batch;
    }
}
