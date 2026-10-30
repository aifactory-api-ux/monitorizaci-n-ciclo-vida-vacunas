<?php

declare(strict_types=1);

namespace App\Domain\Audit\Entity;

use App\Domain\Audit\Service\AuditEventPayloadSchema;
use App\Domain\Shared\Exception\DomainException;
use App\Domain\Shared\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * Entrada del log estructurado e inmutable de operaciones críticas.
 *
 * La entrada se crea por adición y no admite modificaciones posteriores
 * (véase D10). `batch_id` y `user_id` admiten valor nulo para registrar
 * intentos que no se asocian a un lote o a un usuario.
 */
final class LogEntry
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(
        private readonly string $id,
        private readonly ?string $batchId,
        private readonly ?string $userId,
        private readonly string $eventType,
        private readonly array $payload,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    /**
     * Registra una operación crítica.
     *
     * La carga útil se valida contra el esquema del tipo de evento antes de
     * dar por válida la entrada, de modo que nunca se persista un registro
     * con una estructura no interpretable.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws DomainException Si el tipo de evento o su carga útil no son válidos.
     */
    public static function record(
        string $eventType,
        array $payload = [],
        ?string $batchId = null,
        ?string $userId = null,
        ?DateTimeImmutable $createdAt = null,
    ): self {
        AuditEventPayloadSchema::assertValid($eventType, $payload);

        return new self(
            Uuid::v7(),
            $batchId,
            $userId,
            $eventType,
            $payload,
            $createdAt ?? new DateTimeImmutable,
        );
    }

    /**
     * Reconstruye la entrada a partir de su estado persistido.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function reconstitute(
        string $id,
        ?string $batchId,
        ?string $userId,
        string $eventType,
        array $payload,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            $id,
            $batchId,
            $userId,
            $eventType,
            $payload,
            $createdAt,
        );
    }

    /**
     * Identificador UUID de la entrada.
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Lote al que se asocia la entrada, si lo hay.
     */
    public function batchId(): ?string
    {
        return $this->batchId;
    }

    /**
     * Usuario que realiza la operación, si lo hay.
     */
    public function userId(): ?string
    {
        return $this->userId;
    }

    /**
     * Tipo de evento registrado.
     */
    public function eventType(): string
    {
        return $this->eventType;
    }

    /**
     * Carga útil de la entrada.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * Fecha y hora de la entrada.
     */
    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
