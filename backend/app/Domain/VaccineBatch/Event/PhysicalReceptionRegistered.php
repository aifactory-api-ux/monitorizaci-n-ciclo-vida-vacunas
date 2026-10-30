<?php

declare(strict_types=1);

namespace App\Domain\VaccineBatch\Event;

use App\Domain\Shared\Event\DomainEvent;
use App\Domain\VaccineBatch\Entity\VaccineBatch;

/**
 * Evento de registro de la recepción física de un lote.
 *
 * La carga útil publica el identificador del lote y el estado resultante de
 * la recepción, para que el frontend invalide la caché del lote sin tener
 * que deducir el nuevo estado (véase D11 y D12).
 */
final class PhysicalReceptionRegistered implements DomainEvent
{
    /**
     * Nombre del evento en el cable.
     */
    public const EVENT_NAME = 'batch.physical_reception.registered';

    public function __construct(
        private readonly string $batchId,
        private readonly string $phase,
        private readonly string $status,
        private readonly string $currentCampusId,
    ) {}

    /**
     * Construye el evento a partir del lote con la recepción registrada.
     */
    public static function fromBatch(VaccineBatch $batch): self
    {
        return new self(
            $batch->id(),
            $batch->phase()->value(),
            $batch->status()->value(),
            $batch->currentCampusId(),
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
        ];
    }
}
