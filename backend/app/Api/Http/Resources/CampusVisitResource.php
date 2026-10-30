<?php

declare(strict_types=1);

namespace App\Api\Http\Resources;

use App\Domain\VaccineBatch\ValueObject\CampusVisit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación de una visita a un campus en la API.
 *
 * @mixin CampusVisit
 */
class CampusVisitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'phase' => $this->resource->phase()->value(),
            'campus_id' => $this->resource->campusId(),
            'campus_name' => $this->resource->campusName(),
            'since' => $this->resource->since()->format(DATE_ATOM),
        ];
    }
}
