<?php

declare(strict_types=1);

namespace App\Domain\VaccineBatch\Event;

use App\Domain\Shared\Event\DomainEvent;
use App\Domain\VaccineBatch\Entity\VaccineBatch;

/**
 * Evento de alta de un lote de vacuna.
 *
 * Se difunde en el canal de monitorización para que los paneles de la
 * aplicación renueven el listado sin esperar a una consulta manual.
 */
final class BatchCreated implements DomainEvent
{
    /**
     * Nombre del evento en el cable.
     */
    public const EVENT_NAME = 'batch.created';

    public function __construct(
        private readonly string $batchId,
        private readonly string $phase,
        private readonly string $status,
        private readonly string $currentCampusId,
        private readonly string $referenceId,
    ) {}

    /**
     * Construye el evento a partir del lote recién creado.
     */
    public static function fromBatch(VaccineBatch $batch): self
    {
        return new self(
            $batch->id(),
            $batch->phase()->value(),
            $batch->status()->value(),
            $batch->currentCampusId(),
            $batch->referenceId(),
        );
    }

    /**
     * Nombre del evento en el cable.
     */
    public function name(): string
    {
        return self::EVENT_NAME;
    }

    /**
     * Carga útil del evento.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'batch_id' => $this->batchId,
            'phase' => $this->phase,
            'status' => $this->status,
            'current_campus_id' => $this->currentCampusId,
            'reference_id' => $this->referenceId,
        ];
    }
}
