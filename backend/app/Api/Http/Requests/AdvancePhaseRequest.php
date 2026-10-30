<?php

declare(strict_types=1);

namespace App\Api\Http\Requests;

use App\Domain\VaccineBatch\ValueObject\BatchPhase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Solicitud de avance secuencial de fase.
 */
class AdvancePhaseRequest extends FormRequest
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
            'to_phase' => ['required', 'string', Rule::in(BatchPhase::values())],
        ];
    }

    /**
     * Fase de destino de la transición.
     */
    public function toPhase(): BatchPhase
    {
        return BatchPhase::from((string) $this->validated('to_phase'));
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
