<?php

declare(strict_types=1);

namespace App\Api\Http\Resources;

use App\Domain\VaccineBatch\Entity\PhaseTransition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación de una transición de fase en la API.
 *
 * @mixin PhaseTransition
 */
class PhaseTransitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id(),
            'batch_id' => $this->resource->batchId(),
            'from_phase' => $this->resource->fromPhase()->value(),
            'to_phase' => $this->resource->toPhase()->value(),
            'transition_date' => $this->resource->transitionDate()->format(DATE_ATOM),
        ];
    }
}
