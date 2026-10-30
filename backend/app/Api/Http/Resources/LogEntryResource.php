<?php

declare(strict_types=1);

namespace App\Api\Http\Resources;

use App\Domain\Audit\Entity\LogEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación de una entrada del log estructurado en la API.
 *
 * @mixin LogEntry
 */
class LogEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id(),
            'batch_id' => $this->resource->batchId(),
            'user_id' => $this->resource->userId(),
            'event_type' => $this->resource->eventType(),
            'payload' => $this->resource->payload(),
            'created_at' => $this->resource->createdAt()->format(DATE_ATOM),
        ];
    }
}
