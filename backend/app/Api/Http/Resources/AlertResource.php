<?php

declare(strict_types=1);

namespace App\Api\Http\Resources;

use App\Domain\Alert\Entity\Alert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación de una alerta operativa en la API.
 *
 * Expone el tipo de alerta, el lote afectado, la severidad —que representa
 * también el estado de la alerta, porque la tabla `ALERT` no tiene columna de
 * estado (véase D6)—, el mensaje y la fecha y hora de la detección.
 *
 * @mixin Alert
 */
class AlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id(),
            'batch_id' => $this->resource->batchId(),
            'alert_type' => $this->resource->alertType()->value(),
            'severity' => $this->resource->severity()->value(),
            'status' => $this->resource->severity()->value(),
            'message' => $this->resource->message(),
            'created_at' => $this->resource->createdAt()->format(DATE_ATOM),
        ];
    }
}
