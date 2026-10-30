<?php

declare(strict_types=1);

namespace App\Api\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Solicitud de emisión de token de acceso.
 */
class IssueTokenRequest extends FormRequest
{
    /**
     * La ruta es pública: no exige token previo.
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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Correo electrónico del usuario que solicita el token.
     */
    public function email(): string
    {
        return (string) $this->validated('email');
    }

    /**
     * Contraseña del usuario que solicita el token.
     */
    public function password(): string
    {
        return (string) $this->validated('password');
    }
}
