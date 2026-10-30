<?php

declare(strict_types=1);

namespace App\Domain\Audit\Repository;

use App\Domain\Audit\Entity\LogEntry;

/**
 * Puerto de consulta del log estructurado.
 *
 * La escritura sigue siendo exclusivamente de `LogEntryRepository`: este
 * puerto sólo lee las entradas ya registradas para poder exponer el
 * historial auditable de un lote (véase D10).
 */
interface AuditTrailRepository
{
    /**
     * Entradas del lote ordenadas cronológicamente.
     *
     * @return list<LogEntry>
     */
    public function findByBatchId(string $batchId): array;

    /**
     * Entradas de un tipo de evento ordenadas cronológicamente.
     *
     * @return list<LogEntry>
     */
    public function findByEventType(string $eventType): array;

    /**
     * Entradas del historial completo, de la más reciente a la más antigua.
     *
     * @return list<LogEntry>
     */
    public function findAll(int $limit, int $offset = 0): array;

    /**
     * Número total de entradas registradas en el historial.
     */
    public function count(): int;

    /**
     * Entrenda concreta del historial a partir de su UUID.
     */
    public function findById(string $id): ?LogEntry;
}
