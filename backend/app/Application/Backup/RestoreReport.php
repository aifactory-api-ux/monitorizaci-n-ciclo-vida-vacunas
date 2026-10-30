<?php

declare(strict_types=1);

namespace App\Application\Backup;

/**
 * Resultado de una restauración de datos críticos.
 *
 * Declara el manifiesto del artefacto restaurado y el número de filas y de
 * claves que se han repuesto en el almacén de destino.
 */
final class RestoreReport
{
    public function __construct(
        private readonly string $artifactId,
        private readonly int $restoredRows,
        private readonly int $restoredKeys,
    ) {}

    /**
     * Identificador del artefacto restaurado.
     */
    public function artifactId(): string
    {
        return $this->artifactId;
    }

    /**
     * Número de filas repuestas en el almacén de datos.
     */
    public function restoredRows(): int
    {
        return $this->restoredRows;
    }

    /**
     * Número de claves repuestas en el almacén de claves.
     */
    public function restoredKeys(): int
    {
        return $this->restoredKeys;
    }
}
