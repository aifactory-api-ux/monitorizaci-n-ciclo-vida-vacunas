<?php

declare(strict_types=1);

namespace App\Api\Http\Controllers\V1;

use App\Api\Http\Resources\AlertResource;
use App\Api\Http\Resources\BatchResource;
use App\Api\Http\Resources\CampusVisitResource;
use App\Api\Http\Resources\LimsResultResource;
use App\Api\Http\Resources\LogEntryResource;
use App\Api\Http\Resources\PhaseTransitionResource;
use App\Application\Reports\ExportTraceabilityReport;
use App\Domain\Alert\Entity\Alert;
use App\Domain\Audit\Entity\LogEntry;
use App\Domain\Lims\Entity\LimsResult;
use App\Domain\VaccineBatch\Entity\PhaseTransition;
use App\Domain\VaccineBatch\ValueObject\CampusVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Endpoints del reporte de trazabilidad de un lote de vacuna.
 *
 * El reporte reúne la trazabilidad completa del lote y puede exportarse en
 * formatos aptos para auditorías regulatorias (TC011 y D15).
 */
class ReportController
{
    public function __construct(
        private readonly ExportTraceabilityReport $exportTraceabilityReport,
    ) {}

    /**
     * Reporte de trazabilidad del lote en JSON.
     */
    public function show(string $batchId): JsonResponse
    {
        $report = $this->exportTraceabilityReport->report($batchId);
        $batch = $report->batch();

        return response()->json([
            'batch_id' => $batch->id(),
            'reference_id' => $batch->referenceId(),
            'reference_name' => $report->reference()?->name(),
            'reference_family' => $report->reference()?->family(),
            'phase' => $batch->phase()->value(),
            'status' => $batch->status()->value(),
            'batch' => BatchResource::make($batch)->resolve(),
            'campus_sequence' => array_map(
                fn (CampusVisit $visit): array => CampusVisitResource::make($visit)->resolve(),
                $report->campusSequence(),
            ),
            'transitions' => array_map(
                fn (PhaseTransition $transition): array => PhaseTransitionResource::make($transition)->resolve(),
                $report->transitions(),
            ),
            'lims_results' => array_map(
                fn (LimsResult $result): array => LimsResultResource::make($result)->resolve(),
                $report->limsResults(),
            ),
            'alerts' => array_map(
                fn (Alert $alert): array => AlertResource::make($alert)->resolve(),
                $report->alerts(),
            ),
            'entries' => array_map(
                fn (LogEntry $entry): array => LogEntryResource::make($entry)->resolve(),
                $report->entries(),
            ),
        ]);
    }

    /**
     * Reporte de trazabilidad del lote en CSV.
     */
    public function csv(string $batchId): Response
    {
        return $this->download($batchId, 'csv');
    }

    /**
     * Reporte de trazabilidad del lote en PDF.
     */
    public function pdf(string $batchId): Response
    {
        return $this->download($batchId, 'pdf');
    }

    /**
     * Exporta el reporte en el formato indicado y lo devuelve como fichero.
     */
    private function download(string $batchId, string $format): Response
    {
        $exported = $this->exportTraceabilityReport->execute($batchId, $format);

        return response($exported->content(), 200, [
            'Content-Type' => $exported->contentType(),
            'Content-Disposition' => sprintf('attachment; filename="%s"', $exported->filename()),
        ]);
    }
}
