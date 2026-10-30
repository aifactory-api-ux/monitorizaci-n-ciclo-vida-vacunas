<?php

declare(strict_types=1);

namespace App\Domain\VaccineBatch\Event;

use App\Domain\Shared\Event\DomainEvent;

/**
 * Evento de avance de fase de un lote de vacuna.
 *
 * La carga útil incluye la fase de origen, la fase de destino y el campus
 * vigente del lote, que es la información mínima que el frontend necesita
 * para resincronizar el estado del lote (véase D11 y D12).
 */
final class BatchPhaseAdvanced implements DomainEvent
{
    /**
     * Nombre del evento en el cable.
     */
    public const EVENT_NAME = 'batch.phase.advanced';

    public function __construct(
        private readonly string $batchId,
        private readonly string $fromPhase,
        private readonly string $toPhase,
        private readonly string $currentCampusId,
        private readonly string $status,
        private readonly string $transitionId,
    ) {}

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
            'from_phase' => $this->fromPhase,
            'to_phase' => $this->toPhase,
            'current_campus_id' => $this->currentCampusId,
            'status' => $this->status,
            'transition_id' => $this->transitionId,
        ];
    }
}
