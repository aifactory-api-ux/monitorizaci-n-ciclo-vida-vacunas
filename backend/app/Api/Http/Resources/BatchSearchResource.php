<?php

declare(strict_types=1);

namespace App\Api\Http\Resources;

use App\Domain\Search\Entity\BatchSearchItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación de un lote en la búsqueda avanzada.
 *
 * Añade a los datos del lote el nombre y la familia del producto de
 * referencia, que son los que permiten distinguir los lotes entre sí en el
 * listado (véase D14).
 *
 * @mixin BatchSearchItem
 */
class BatchSearchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->batch()->id(),
            'reference_id' => $this->resource->batch()->referenceId(),
            'reference_name' => $this->resource->referenceName(),
            'reference_family' => $this->resource->referenceFamily(),
            'phase' => $this->resource->batch()->phase()->value(),
            'status' => $this->resource->batch()->status()->value(),
            'current_campus_id' => $this->resource->batch()->currentCampusId(),
            'created_at' => $this->resource->batch()->createdAt()->format(DATE_ATOM),
            'updated_at' => $this->resource->batch()->updatedAt()->format(DATE_ATOM),
        ];
    }
}
