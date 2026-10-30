<?php

declare(strict_types=1);

namespace App\Domain\Alert\Repository;

use App\Domain\Alert\Entity\Alert;

/**
 * Puerto de persistencia de las alertas operativas.
 */
interface AlertRepository
{
    /**
     * Persiste la alerta, insertándola si es nueva.
     */
    public function save(Alert $alert): void;

    /**
     * Todas las alertas registradas, ordenadas cronológicamente.
     *
     * @return list<Alert>
     */
    public function findAll(): array;

    /**
     * Alertas del lote indicadas, ordenadas cronológicamente.
     *
     * @return list<Alert>
     */
    public function findByBatchId(string $batchId): array;
}
