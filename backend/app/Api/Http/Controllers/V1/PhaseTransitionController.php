<?php

declare(strict_types=1);

namespace App\Api\Http\Controllers\V1;

use App\Api\Http\Requests\AdvancePhaseRequest;
use App\Api\Http\Resources\BatchResource;
use App\Api\Http\Resources\PhaseTransitionResource;
use App\Application\Batches\AdvanceBatchPhase;
use Illuminate\Http\JsonResponse;

/**
 * Endpoints de las transiciones de fase del lote de vacuna.
 */
class PhaseTransitionController
{
    public function __construct(
        private readonly AdvanceBatchPhase $advanceBatchPhase,
    ) {}

    /**
     * Avanza el lote a la fase siguiente del ciclo de vida.
     */
    public function store(AdvancePhaseRequest $request, string $batchId): JsonResponse
    {
        $result = $this->advanceBatchPhase->execute(
            $batchId,
            $request->toPhase(),
            $request->actorId(),
        );

        return response()->json([
            'batch' => BatchResource::make($result->batch())->resolve(),
            'transition' => PhaseTransitionResource::make($result->transition())->resolve(),
        ]);
    }
}
