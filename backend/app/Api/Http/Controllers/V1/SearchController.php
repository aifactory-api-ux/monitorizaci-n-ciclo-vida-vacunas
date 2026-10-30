<?php

declare(strict_types=1);

namespace App\Api\Http\Controllers\V1;

use App\Api\Http\Requests\BatchSearchRequest;
use App\Api\Http\Resources\BatchSearchResource;
use App\Application\Search\BatchSearchService;
use App\Domain\Search\Entity\BatchSearchItem;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint de búsqueda avanzada de lotes.
 */
class SearchController
{
    public function __construct(
        private readonly BatchSearchService $batchSearch,
    ) {}

    /**
     * Página de lotes que satisfacen los filtros solicitados.
     *
     * La respuesta incluye el total de coincidencias del filtro, que es
     * independiente de la página solicitada (véase D14).
     */
    public function index(BatchSearchRequest $request): JsonResponse
    {
        $result = $this->batchSearch->execute($request->criteria());

        return response()->json([
            'batches' => array_map(
                fn (BatchSearchItem $item): array => BatchSearchResource::make($item)->resolve(),
                $result->items(),
            ),
            'total' => $result->total(),
            'page' => $result->page(),
            'per_page' => $result->perPage(),
            'last_page' => $result->lastPage(),
        ]);
    }
}
