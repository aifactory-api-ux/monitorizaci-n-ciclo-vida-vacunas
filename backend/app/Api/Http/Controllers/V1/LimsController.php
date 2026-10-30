<?php

declare(strict_types=1);

namespace App\Api\Http\Controllers\V1;

use App\Api\Http\Requests\ImportLimsResultsRequest;
use App\Api\Http\Requests\RegisterLimsResultRequest;
use App\Api\Http\Resources\LimsResultResource;
use App\Application\Lims\ImportLimsResults;
use App\Application\Lims\RegisterManualLimsResult;
use Illuminate\Http\JsonResponse;

/**
 * Endpoints de integración con LIMS Labware.
 */
class LimsController
{
    public function __construct(
        private readonly ImportLimsResults $importLimsResults,
        private readonly RegisterManualLimsResult $registerManualLimsResult,
    ) {}

    /**
     * Importa las cargas de resultados disponibles en LIMS Labware.
     *
     * Las cargas consistentes se persisten; las inconsistentes se descartan y
     * su intento queda registrado con el detalle del error (véase D8).
     */
    public function import(ImportLimsResultsRequest $request): JsonResponse
    {
        $summary = $this->importLimsResults->execute($request->batchId(), $request->actorId());

        return response()->json([
            'summary' => [
                'imported_batches' => $summary->importedBatches(),
                'imported_results' => $summary->importedResults(),
                'rejected_payloads' => $summary->rejectedPayloads(),
                'rejections' => $summary->rejections(),
            ],
        ]);
    }

    /**
     * Registra un resultado microbiológico indicado por el Técnico de Control
     * de Calidad.
     */
    public function store(RegisterLimsResultRequest $request, string $batchId): JsonResponse
    {
        $result = $this->registerManualLimsResult->execute(
            $batchId,
            $request->testType(),
            $request->result(),
            $request->approvedBy(),
            $request->resultDate(),
            $request->actorId(),
        );

        return LimsResultResource::make($result)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
