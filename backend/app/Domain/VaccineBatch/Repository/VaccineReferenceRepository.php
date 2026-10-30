<?php

declare(strict_types=1);

namespace App\Domain\VaccineBatch\Repository;

use App\Domain\VaccineBatch\Entity\VaccineReference;

/**
 * Puerto de consulta del catálogo de referencias de vacuna.
 */
interface VaccineReferenceRepository
{
    /**
     * Recupera la referencia por su identificador, o null si no existe.
     */
    public function findById(string $referenceId): ?VaccineReference;
}
