<?php

declare(strict_types=1);

namespace App\Api\Http\Resources;

use App\Domain\VaccineBatch\Entity\VaccineBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación de un lote de vacuna en la API.
 *
 * @mixin VaccineBatch
 */
class BatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id(),
            'reference_id' => $this->resource->referenceId(),
            'phase' => $this->resource->phase()->value(),
            'status' => $this->resource->status()->value(),
            'current_campus_id' => $this->resource->currentCampusId(),
            'created_at' => $this->resource->createdAt()->format(DATE_ATOM),
            'updated_at' => $this->resource->updatedAt()->format(DATE_ATOM),
        ];
    }
}
