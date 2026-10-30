<?php

declare(strict_types=1);

namespace App\Api\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Solicitud de importación de resultados de LIMS Labware.
 */
class ImportLimsResultsRequest extends FormRequest
{
    /**
     * La autorización por rol se aplica en la capa de aplicación (véase D9).
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
        return [
            'batch_id' => ['sometimes', 'nullable', 'string', 'uuid'],
        ];
    }

    /**
     * Lote concreto a importar, o null para importar todas las cargas.
     */
    public function batchId(): ?string
    {
        $batchId = $this->validated('batch_id');

        return is_string($batchId) ? $batchId : null;
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
