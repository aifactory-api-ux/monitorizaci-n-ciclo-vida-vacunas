<?php

declare(strict_types=1);

namespace App\Api\Http\Requests;

use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Solicitud de registro manual de un resultado microbiológico.
 */
class RegisterLimsResultRequest extends FormRequest
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
            'test_type' => ['required', 'string', 'max:100'],
            'result' => ['required', 'string', 'max:50'],
            'approved_by' => ['nullable', 'string', 'max:150'],
            'result_date' => ['required', 'date'],
        ];
    }

    /**
     * Tipo de análisis registrado.
     */
    public function testType(): string
    {
        return (string) $this->validated('test_type');
    }

    /**
     * Literal del resultado registrado.
     */
    public function result(): string
    {
        return (string) $this->validated('result');
    }

    /**
     * Identificador de quien aprueba el resultado, si lo hay.
     */
    public function approvedBy(): ?string
    {
        $approvedBy = $this->validated('approved_by');

        if (! is_string($approvedBy) || trim($approvedBy) === '') {
            return null;
        }

        return trim($approvedBy);
    }

    /**
     * Fecha del resultado registrado.
     *
     * La regla de validación `date` garantiza que el valor sea interpretable.
     */
    public function resultDate(): DateTimeImmutable
    {
        return new DateTimeImmutable((string) $this->validated('result_date'));
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
