<?php

declare(strict_types=1);

namespace App\Application\Audit;

use App\Domain\Audit\Entity\LogEntry;

/**
 * Página del historial inmutable de auditoría.
 */
final class AuditTrailPage
{
    /**
     * @param  list<LogEntry>  $entries
     */
    public function __construct(
        private readonly array $entries,
        private readonly int $total,
        private readonly int $page,
        private readonly int $perPage,
    ) {}

    /**
     * Entradas de la página, de la más reciente a la más antigua.
     *
     * @return list<LogEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * Número total de entradas registradas en el historial.
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * Página solicitada.
     */
    public function page(): int
    {
        return $this->page;
    }

    /**
     * Número de entradas por página.
     */
    public function perPage(): int
    {
        return $this->perPage;
    }
}
