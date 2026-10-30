<?php

declare(strict_types=1);

namespace App\Domain\Audit\Service;

use App\Domain\Audit\Entity\LogEntry;
use App\Domain\Audit\Repository\LogEntryRepository;
use DateTimeImmutable;

/**
 * Camino único de escritura del log estructurado e inmutable de auditoría.
 *
 * Toda operación crítica del dominio se registra exclusivamente a través de
 * este servicio, que valida la carga útil contra el esquema del tipo de
 * evento antes de persistir la entrada (D10, TC026).
 */
final class StructuredAuditLogger
{
    public function __construct(
        private readonly LogEntryRepository $logEntries,
    ) {}

    /**
     * Registra una operación crítica y devuelve la entrada persistida.
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(
        string $eventType,
        array $payload,
        ?string $batchId = null,
        ?string $userId = null,
        ?DateTimeImmutable $createdAt = null,
    ): LogEntry {
        AuditEventPayloadSchema::assertValid($eventType, $payload);

        $entry = LogEntry::record($eventType, $payload, $batchId, $userId, $createdAt);

        $this->logEntries->append($entry);

        return $entry;
    }
}
