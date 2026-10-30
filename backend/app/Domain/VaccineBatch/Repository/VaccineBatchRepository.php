<?php

declare(strict_types=1);

namespace App\Domain\VaccineBatch\Repository;

use App\Domain\VaccineBatch\Entity\VaccineBatch;

/**
 * Puerto de persistencia del lote de vacuna.
 */
interface VaccineBatchRepository
{
    /**
     * Persiste el lote, insertándolo si es nuevo.
     */
    public function save(VaccineBatch $batch): void;

    /**
     * Recupera el lote por su identificador, o null si no existe.
     */
    public function findById(string $batchId): ?VaccineBatch;

    /**
     * Recupera todos los lotes registrados.
     *
     * @return list<VaccineBatch>
     */
    public function findAll(): array;
}
