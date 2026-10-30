<?php

declare(strict_types=1);

namespace App\Api\Http\Controllers\V1;

use App\Api\Http\Resources\AlertResource;
use App\Api\Http\Resources\BatchResource;
use App\Api\Http\Resources\CampusVisitResource;
use App\Api\Http\Resources\LimsResultResource;
use App\Api\Http\Resources\LogEntryResource;
use App\Api\Http\Resources\PhaseTransitionResource;
use App\Application\Batches\GetBatchTraceability;
use App\Domain\Alert\Entity\Alert;
use App\Domain\Audit\Entity\LogEntry;
use App\Domain\Lims\Entity\LimsResult;
use App\Domain\VaccineBatch\Entity\PhaseTransition;
use App\Domain\VaccineBatch\ValueObject\CampusVisit;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint de trazabilidad completa de un lote de vacuna.
 *
 * Expone el recorrido del lote entre campus, sus transiciones de fase
 * ordenadas cronológicamente, los resultados de LIMS, las alertas y el
 * historial de auditoría (TC003).
 */
class TraceabilityController
{
    public function __construct(
        private readonly GetBatchTraceability $getBatchTraceability,
    ) {}

    /**
     * Trazabilidad completa del lote indicado.
     */
    public function show(string $batchId): JsonResponse
    {
        $traceability = $this->getBatchTraceability->execute($batchId);
        $batch = $traceability->batch();

        return response()->json([
            'batch_id' => $batch->id(),
            'phase' => $batch->phase()->value(),
            'status' => $batch->status()->value(),
            'current_campus_id' => $batch->currentCampusId(),
            'batch' => BatchResource::make($batch)->resolve(),
            'campus_sequence' => array_map(
                fn (CampusVisit $visit): array => CampusVisitResource::make($visit)->resolve(),
                $traceability->campusSequence(),
            ),
            'transitions' => array_map(
                fn (PhaseTransition $transition): array => PhaseTransitionResource::make($transition)->resolve(),
                $traceability->transitions(),
            ),
            'lims_results' => array_map(
                fn (LimsResult $result): array => LimsResultResource::make($result)->resolve(),
                $traceability->limsResults(),
            ),
            'alerts' => array_map(
                fn (Alert $alert): array => AlertResource::make($alert)->resolve(),
                $traceability->alerts(),
            ),
            'entries' => array_map(
                fn (LogEntry $entry): array => LogEntryResource::make($entry)->resolve(),
                $traceability->entries(),
            ),
        ]);
    }
}
