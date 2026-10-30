<?php

declare(strict_types=1);

namespace App\Domain\Alert\Entity;

use App\Domain\Alert\ValueObject\AlertSeverity;
use App\Domain\Alert\ValueObject\AlertType;
use App\Domain\Shared\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * Alerta operativa de prioridad máxima sobre un lote.
 *
 * La tabla `ALERT` del contrato de esquema tiene exactamente los campos `id`,
 * `batch_id`, `alert_type`, `severity`, `message` y `created_at`: no existe
 * columna de estado, de modo que el estado de la alerta se representa con la
 * severidad persistida (véase D6).
 */
final class Alert
{
    /**
     * Tipo de evento del log estructurado para una alerta generada.
     */
    public const EVENT_TYPE_RAISED = 'alert.raised';

    private function __construct(
        private readonly string $id,
        private readonly string $batchId,
        private readonly AlertType $alertType,
        private readonly AlertSeverity $severity,
        private readonly string $message,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    /**
     * Genera una alerta sobre un lote.
     */
    public static function raise(
        string $batchId,
        AlertType $alertType,
        AlertSeverity $severity,
        string $message,
        ?DateTimeImmutable $createdAt = null,
    ): self {
        return new self(
            Uuid::v7(),
            $batchId,
            $alertType,
            $severity,
            $message,
            $createdAt ?? new DateTimeImmutable,
        );
    }

    /**
     * Reconstruye la alerta a partir de su estado persistido.
     */
    public static function reconstitute(
        string $id,
        string $batchId,
        AlertType $alertType,
        AlertSeverity $severity,
        string $message,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            $id,
            $batchId,
            $alertType,
            $severity,
            $message,
            $createdAt,
        );
    }

    /**
     * Identificador UUID de la alerta.
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Identificador UUID del lote afectado.
     */
    public function batchId(): string
    {
        return $this->batchId;
    }

    /**
     * Tipo de la alerta.
     */
    public function alertType(): AlertType
    {
        return $this->alertType;
    }

    /**
     * Severidad de la alerta.
     */
    public function severity(): AlertSeverity
    {
        return $this->severity;
    }

    /**
     * Mensaje que describe la incidencia detectada.
     */
    public function message(): string
    {
        return $this->message;
    }

    /**
     * Fecha y hora de la detección.
     */
    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Indica si la alerta es de prioridad máxima.
     */
    public function isCritical(): bool
    {
        return $this->severity->isCritical();
    }
}
