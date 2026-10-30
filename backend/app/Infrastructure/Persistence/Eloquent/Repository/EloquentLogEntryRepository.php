<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repository;

use App\Domain\Audit\Entity\LogEntry;
use App\Domain\Audit\Repository\LogEntryRepository;
use App\Infrastructure\Logging\JsonAuditLogger;
use App\Infrastructure\Persistence\Eloquent\Model\LogEntryModel;

/**
 * Adaptador Eloquent del puerto de escritura del log estructurado.
 *
 * Sólo expone la operación de alta: no hay vía de actualización ni de borrado
 * de entradas de auditoría (véase D10).
 */
final class EloquentLogEntryRepository implements LogEntryRepository
{
    public function __construct(private readonly JsonAuditLogger $auditLogger) {}

    public function append(LogEntry $entry): void
    {
        LogEntryModel::query()->create([
            'id' => $entry->id(),
            'batch_id' => $entry->batchId(),
            'user_id' => $entry->userId(),
            'event_type' => $entry->eventType(),
            'payload' => $entry->payload(),
            'created_at' => $entry->createdAt(),
        ]);

        // La misma entrada se emite en JSON estructurado por stdout (D10).
        $this->auditLogger->emit($entry);
    }
}
