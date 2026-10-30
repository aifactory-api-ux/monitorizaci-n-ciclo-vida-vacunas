<?php

declare(strict_types=1);

namespace App\Api\Http\Resources;

use App\Application\Lims\GatingStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación del estado del gating microbiológico de un lote.
 *
 * El frontend recibe la decisión ya resuelta y no calcula la regla (véase D5).
 *
 * @mixin GatingStatus
 */
class GatingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $approved = $this->resource->approvedResult();

        return [
            'batch_id' => $this->resource->batchId(),
            'approved' => $this->resource->isApproved(),
            'reason' => $this->resource->reason(),
            'approved_by' => $approved?->approvedBy(),
            'source' => $approved?->source(),
            'updated_at' => $approved?->updatedAt()->format(DATE_ATOM),
        ];
    }
}
