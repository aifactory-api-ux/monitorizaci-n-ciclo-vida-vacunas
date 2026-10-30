<?php

declare(strict_types=1);

namespace App\Application\Reports;

use App\Domain\Report\Port\TraceabilityReportExporter;
use App\Domain\Shared\Exception\DomainException;

/**
 * Registro de exportadores de trazabilidad por formato.
 *
 * La composición de los exportadores se resuelve en el proveedor de la
 * aplicación, de forma que la capa Application sólo conoce el puerto del
 * dominio.
 */
final class ReportExporterRegistry
{
    /**
     * @param  array<string, TraceabilityReportExporter>  $exporters
     */
    public function __construct(
        private readonly array $exporters,
    ) {}

    /**
     * Exportador asociado al formato indicado.
     *
     * @throws DomainException Si el formato no está soportado.
     */
    public function exporterFor(string $format): TraceabilityReportExporter
    {
        $exporter = $this->exporters[strtolower($format)] ?? null;

        if ($exporter === null) {
            throw DomainException::unknownReportFormat($format);
        }

        return $exporter;
    }

    /**
     * Formatos soportados.
     *
     * @return list<string>
     */
    public function formats(): array
    {
        return array_keys($this->exporters);
    }
}
