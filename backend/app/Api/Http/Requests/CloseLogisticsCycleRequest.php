<?php

declare(strict_types=1);

namespace App\Api\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Solicitud de cierre del ciclo logístico de un lote.
 *
 * El contrato de esquema no define columnas para el cierre, por lo que la
 * operación no transporta carga útil: sólo el lote y el usuario que lo cierra.
 */
class CloseLogisticsCycleRequest extends FormRequest
{
    /**
     * La autorización por rol se aplica en la capa de aplicación (véase D9).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Identificador del usuario autenticado que realiza la operación.
     */
    public function actorId(): ?string
    {
        $identifier = $this->user()?->getAuthIdentifier();

        return $identifier === null ? null : (string) $identifier;
    }
}
