<?php

declare(strict_types=1);

namespace App\Application\Reports;

use App\Domain\Report\Entity\TraceabilityReport;
use App\Domain\Report\Service\TraceabilityReportBuilder;
use App\Domain\Shared\Exception\ResourceNotFoundException;

/**
 * Caso de uso de exportación del reporte de trazabilidad de un lote.
 *
 * Compone el modelo de trazabilidad y lo delega en el exportador del formato
 * solicitado (D15).
 */
final class ExportTraceabilityReport
{
    public function __construct(
        private readonly TraceabilityReportBuilder $builder,
        private readonly ReportExporterRegistry $exporters,
    ) {}

    /**
     * Modelo de trazabilidad del lote, sin serializar.
     *
     * @throws ResourceNotFoundException Si el lote no existe.
     */
    public function report(string $batchId): TraceabilityReport
    {
        return $this->builder->build($batchId);
    }

    /**
     * Exporta el reporte de trazabilidad del lote en el formato indicado.
     *
     * @throws ResourceNotFoundException Si el lote no existe.
     */
    public function execute(string $batchId, string $format): ExportedReport
    {
        $report = $this->builder->build($batchId);

        $exporter = $this->exporters->exporterFor($format);

        return new ExportedReport(
            $exporter->export($report),
            $exporter->filename($report),
            $exporter->contentType(),
        );
    }
}
