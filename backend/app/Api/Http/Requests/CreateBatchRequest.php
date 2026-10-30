<?php

declare(strict_types=1);

namespace App\Api\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Solicitud de alta de lote de vacuna.
 */
class CreateBatchRequest extends FormRequest
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
        return [
            'reference_id' => ['required', 'string', 'uuid'],
        ];
    }

    /**
     * Identificador de la referencia de vacuna del lote.
     */
    public function referenceId(): string
    {
        return (string) $this->validated('reference_id');
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
