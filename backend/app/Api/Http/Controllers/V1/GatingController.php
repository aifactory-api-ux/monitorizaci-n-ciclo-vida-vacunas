<?php

declare(strict_types=1);

namespace App\Api\Http\Controllers\V1;

use App\Api\Http\Requests\ForceGatingReleaseRequest;
use App\Api\Http\Resources\GatingResource;
use App\Application\Lims\EvaluateGatingRelease;
use App\Application\Lims\ForceGatingRelease;
use Illuminate\Http\JsonResponse;

/**
 * Endpoints del gating microbiológico del lote.
 */
class GatingController
{
    public function __construct(
        private readonly EvaluateGatingRelease $evaluateGating,
        private readonly ForceGatingRelease $forceGatingRelease,
    ) {}

    /**
     * Estado del gating microbiológico del lote.
     */
    public function show(string $batchId): JsonResponse
    {
        $status = $this->evaluateGating->execute($batchId);

        return response()->json([
            'gating' => GatingResource::make($status)->resolve(),
        ]);
    }

    /**
     * Intento de forzar la liberación del lote.
     *
     * La operación está siempre denegada: sólo la aprobación microbiológica
     * recibida de LIMS Labware libera un lote (véase TC016).
     */
    public function release(ForceGatingReleaseRequest $request, string $batchId): never
    {
        $this->forceGatingRelease->execute($batchId, $request->actorId());
    }
}
