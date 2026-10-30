<?php

declare(strict_types=1);

namespace App\Api\Http\Resources;

use App\Domain\Lims\Entity\LimsResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación de un resultado de LIMS en la API.
 *
 * Expone el origen del dato y su fecha de actualización, que son los datos de
 * trazabilidad que la tabla `LIMS_RESULT` no almacena (véase D6).
 *
 * @mixin LimsResult
 */
class LimsResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id(),
            'batch_id' => $this->resource->batchId(),
            'test_type' => $this->resource->testType(),
            'result' => $this->resource->result(),
            'approved_by' => $this->resource->approvedBy(),
            'result_date' => $this->resource->resultDate()->format(DATE_ATOM),
            'source' => $this->resource->source(),
            'updated_at' => $this->resource->updatedAt()->format(DATE_ATOM),
        ];
    }
}
