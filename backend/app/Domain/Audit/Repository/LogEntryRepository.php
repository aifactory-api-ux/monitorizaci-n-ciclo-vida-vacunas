<?php

declare(strict_types=1);

namespace App\Domain\Audit\Repository;

use App\Domain\Audit\Entity\LogEntry;

/**
 * Puerto de escritura del log estructurado.
 *
 * Sólo expone la operación de alta: no existe vía de actualización ni de
 * borrado de entradas (véase D10).
 */
interface LogEntryRepository
{
    /**
     * Añade una entrada al log.
     */
    public function append(LogEntry $entry): void;
}
