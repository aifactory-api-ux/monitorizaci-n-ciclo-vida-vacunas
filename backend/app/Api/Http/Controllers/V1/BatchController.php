<?php

declare(strict_types=1);

namespace App\Api\Http\Controllers\V1;

use App\Api\Http\Requests\CloseLogisticsCycleRequest;
use App\Api\Http\Requests\CreateBatchRequest;
use App\Api\Http\Requests\RegisterPhysicalReceptionRequest;
use App\Api\Http\Resources\BatchResource;
use App\Api\Http\Resources\GatingResource;
use App\Api\Http\Resources\LimsResultResource;
use App\Application\Authorization\ActorRoleResolver;
use App\Application\Batches\CloseLogisticsCycle;
use App\Application\Batches\CreateBatch;
use App\Application\Batches\GetBatchDetail;
use App\Application\Batches\ListBatches;
use App\Application\Batches\RegisterPhysicalReception;
use App\Domain\Lims\Entity\LimsResult;
use App\Domain\User\ValueObject\UserRole;
use App\Domain\VaccineBatch\Entity\VaccineBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints del ciclo de vida del lote de vacuna.
 */
class BatchController
{
    public function __construct(
        private readonly CreateBatch $createBatch,
        private readonly RegisterPhysicalReception $registerPhysicalReception,
        private readonly CloseLogisticsCycle $closeLogisticsCycle,
        private readonly ListBatches $listBatches,
        private readonly GetBatchDetail $getBatchDetail,
        private readonly ActorRoleResolver $actorRoles,
    ) {}

    /**
     * Da de alta un lote en la fase de cultivo.
     */
    public function store(CreateBatchRequest $request): JsonResponse
    {
        $batch = $this->createBatch->execute($request->referenceId(), $request->actorId());

        return BatchResource::make($batch)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    /**
     * Registra la recepción física del lote en el Campus 3.
     */
    public function reception(RegisterPhysicalReceptionRequest $request, string $batchId): JsonResponse
    {
        $batch = $this->registerPhysicalReception->execute($batchId, $request->actorId());

        return BatchResource::make($batch)->response();
    }

    /**
     * Cierra el ciclo logístico del lote.
     */
    public function logisticsClose(CloseLogisticsCycleRequest $request, string $batchId): JsonResponse
    {
        $batch = $this->closeLogisticsCycle->execute($batchId, $request->actorId());

        return BatchResource::make($batch)->response();
    }

    /**
     * Lista los lotes registrados con su estado de gating.
     *
     * El listado se acota al alcance de visibilidad del rol solicitante.
     */
    public function index(Request $request): JsonResponse
    {
        $batches = $this->listBatches->execute($this->roleOf($request));

        return response()->json([
            'batches' => array_map(
                fn (VaccineBatch $batch): array => BatchResource::make($batch)->resolve(),
                $batches,
            ),
        ]);
    }

    /**
     * Detalle de un lote: estado, resultados de LIMS y gating microbiológico.
     *
     * Un lote fuera del alcance de visibilidad del rol responde 404.
     */
    public function show(Request $request, string $batchId): JsonResponse
    {
        $detail = $this->getBatchDetail->execute($batchId, $this->roleOf($request));

        return response()->json([
            'batch' => BatchResource::make($detail->batch())->resolve(),
            'lims_results' => array_map(
                fn (LimsResult $result): array => LimsResultResource::make($result)->resolve(),
                $detail->limsResults(),
            ),
            'gating' => GatingResource::make($detail->gating())->resolve(),
        ]);
    }

    /**
     * Rol del usuario autenticado en la petición.
     */
    private function roleOf(Request $request): UserRole
    {
        return $this->actorRoles->resolve($request->user()?->getAuthIdentifier());
    }
}
