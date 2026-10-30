<?php

declare(strict_types=1);

namespace App\Api\Http\Controllers\V1;

use App\Api\Http\Requests\IssueTokenRequest;
use App\Application\Authentication\IssueAccessToken;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint público de emisión del token de acceso del panel.
 *
 * Devuelve un token JWT que el cliente envía en la cabecera `Authorization`
 * de las peticiones de datos (véase D9 y NFR003).
 */
class AuthController
{
    public function __construct(
        private readonly IssueAccessToken $issueAccessToken,
    ) {}

    /**
     * Emite el token de acceso de las credenciales recibidas.
     */
    public function store(IssueTokenRequest $request): JsonResponse
    {
        $token = $this->issueAccessToken->execute($request->email(), $request->password());

        return response()->json([
            'token_type' => $token->tokenType(),
            'access_token' => $token->accessToken(),
            'expires_in' => $token->expiresIn(),
        ]);
    }
}
