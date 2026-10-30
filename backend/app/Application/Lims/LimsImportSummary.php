<?php

declare(strict_types=1);

namespace App\Application\Lims;

/**
 * Resumen de una ejecución de importación de resultados de LIMS Labware.
 */
final class LimsImportSummary
{
    /**
     * @param  list<array{error_code: string, error_detail: string, batch_reference: string|null}>  $rejections
     */
    public function __construct(
        private readonly int $importedBatches,
        private readonly int $importedResults,
        private readonly array $rejections = [],
    ) {}

    /**
     * Lotes dados de alta o actualizados con la carga importada.
     */
    public function importedBatches(): int
    {
        return $this->importedBatches;
    }

    /**
     * Resultados microbiológicos y analíticos persistidos.
     */
    public function importedResults(): int
    {
        return $this->importedResults;
    }

    /**
     * Cargas descartadas por ser inconsistentes.
     */
    public function rejectedPayloads(): int
    {
        return count($this->rejections);
    }

    /**
     * Detalle de las cargas descartadas.
     *
     * @return list<array{error_code: string, error_detail: string, batch_reference: string|null}>
     */
    public function rejections(): array
    {
        return $this->rejections;
    }
}
