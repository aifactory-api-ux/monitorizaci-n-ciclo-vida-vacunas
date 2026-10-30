<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use App\Domain\Audit\Entity\LogEntry;
use Psr\Log\LoggerInterface;

/**
 * Emisor del log de auditoría en JSON estructurado.
 *
 * Publica en el canal de logs la misma entrada que queda persistida en
 * LOG_ENTRY, de modo que la traza pueda ser recolectada por el agregador sin
 * consultar la base de datos (véase D10). La emisión es paralela al alta y
 * nunca sustituye a la persistencia: el registro inmutable es la fuente de
 * verdad.
 */
final class JsonAuditLogger
{
    public function __construct(private readonly LoggerInterface $logger) {}

    /**
     * Emite la entrada de auditoría en formato JSON estructurado.
     */
    public function emit(LogEntry $entry): void
    {
        $this->logger->info('audit.entry', [
            'id' => $entry->id(),
            'batch_id' => $entry->batchId(),
            'user_id' => $entry->userId(),
            'event_type' => $entry->eventType(),
            'payload' => $entry->payload(),
            'created_at' => $entry->createdAt()->format(DATE_ATOM),
        ]);
    }
}
