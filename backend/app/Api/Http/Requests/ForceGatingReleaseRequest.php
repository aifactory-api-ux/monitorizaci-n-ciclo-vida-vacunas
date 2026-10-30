<?php

declare(strict_types=1);

namespace App\Api\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Solicitud de forzaje de la liberación de un lote.
 *
 * La operación está siempre denegada (véase D5 y TC016); la solicitud existe
 * para dejar constancia de quién intentó forzarla.
 */
class ForceGatingReleaseRequest extends FormRequest
{
    /**
     * La operación está denegada a todos los roles: la autoriza el dominio.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
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
