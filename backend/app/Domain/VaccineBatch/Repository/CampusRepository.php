<?php

declare(strict_types=1);

namespace App\Domain\VaccineBatch\Repository;

use App\Domain\VaccineBatch\Entity\Campus;

/**
 * Puerto de consulta del catálogo de campus.
 */
interface CampusRepository
{
    /**
     * Recupera el campus por su identificador, o null si no existe.
     */
    public function findById(string $campusId): ?Campus;

    /**
     * Recupera el campus por su nombre, o null si no existe.
     */
    public function findByName(string $campusName): ?Campus;
}
