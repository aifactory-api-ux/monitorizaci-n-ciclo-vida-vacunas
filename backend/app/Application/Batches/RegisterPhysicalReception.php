<?php

declare(strict_types=1);

namespace App\Application\Batches;

use App\Domain\Audit\Service\StructuredAuditLogger;
use App\Domain\Search\Port\BatchSearchCache;
use App\Domain\Shared\Event\DomainEventRecorder;
use App\Domain\Shared\Exception\DomainException;
use App\Domain\Shared\Exception\ResourceNotFoundException;
use App\Domain\VaccineBatch\Entity\VaccineBatch;
use App\Domain\VaccineBatch\Event\PhysicalReceptionRegistered;
use App\Domain\VaccineBatch\Repository\VaccineBatchRepository;
use DateTimeImmutable;

/**
 * Caso de uso de registro de la recepción física de un lote en el Campus 3.
 *
 * La recepción deja el lote en el estado "Recibido" (véase TC017).
 */
final class RegisterPhysicalReception
{
    /**
     * Tipo de evento registrado en el log estructurado.
     */
    public const EVENT_TYPE = 'batch.physical_reception.registered';

    public function __construct(
        private readonly VaccineBatchRepository $batches,
        private readonly StructuredAuditLogger $auditLogger,
        private readonly BatchSearchCache $searchCache,
        private readonly DomainEventRecorder $events,
    ) {}

    /**
     * Registra la recepción física del lote.
     *
     * @throws ResourceNotFoundException Si el lote no existe.
     * @throws DomainException Si el lote no está en la fase logística.
     */
    public function execute(string $batchId, ?string $actorId = null, ?DateTimeImmutable $now = null): VaccineBatch
    {
        $batch = $this->batches->findById($batchId);

        if ($batch === null) {
            throw ResourceNotFoundException::batch($batchId);
        }

        $timestamp = $now ?? new DateTimeImmutable;
        $batch->registerPhysicalReception($timestamp);

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

        // La recepción se difunde en el canal de monitorización con el estado
        // resultante del lote (véase D11).
        $this->events->record(PhysicalReceptionRegistered::fromBatch($batch));

        return $batch;
    }
}
