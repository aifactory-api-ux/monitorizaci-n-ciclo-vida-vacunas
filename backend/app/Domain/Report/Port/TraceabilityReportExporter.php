<?php

declare(strict_types=1);

namespace App\Domain\Report\Port;

use App\Domain\Report\Entity\TraceabilityReport;

/**
 * Puerto de serialización del modelo de trazabilidad.
 *
 * Cada adaptador de infraestructura implementa un formato de exportación
 * apto para auditorías regulatorias (D15).
 */
interface TraceabilityReportExporter
{
    /**
     * Serializa el modelo de trazabilidad al formato del adaptador.
     */
    public function export(TraceabilityReport $report): string;

    /**
     * Nombre del fichero de la exportación.
     */
    public function filename(TraceabilityReport $report): string;

    /**
     * Tipo de contenido MIME de la exportación.
     */
    public function contentType(): string;
}
