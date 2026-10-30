<?php

declare(strict_types=1);

namespace App\Domain\Alert\Event;

use App\Domain\Alert\Entity\Alert;
use App\Domain\Shared\Event\DomainEvent;

/**
 * Evento de alerta generada sobre un lote.
 *
 * La carga útil identifica la alerta, el lote afectado y su severidad, de
 * modo que el frontend invalide la caché de alertas y de indicadores
 * (véase D11 y D12).
 */
final class AlertRaised implements DomainEvent
{
    /**
     * Nombre del evento en el cable.
     */
    public const EVENT_NAME = 'alert.raised';

    public function __construct(
        private readonly string $alertId,
        private readonly string $batchId,
        private readonly string $alertType,
        private readonly string $severity,
        private readonly string $message,
        private readonly string $detectedAt,
    ) {}

    /**
     * Construye el evento a partir de la alerta persistida.
     */
    public static function fromAlert(Alert $alert): self
    {
        return new self(
            $alert->id(),
            $alert->batchId(),
            $alert->alertType()->value(),
            $alert->severity()->value(),
            $alert->message(),
            $alert->createdAt()->format(DATE_ATOM),
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
            'alert_id' => $this->alertId,
            'batch_id' => $this->batchId,
            'alert_type' => $this->alertType,
            'severity' => $this->severity,
            'message' => $this->message,
            'detected_at' => $this->detectedAt,
        ];
    }
}
