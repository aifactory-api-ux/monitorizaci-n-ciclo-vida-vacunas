<?php

declare(strict_types=1);

namespace App\Domain\Authentication\ValueObject;

/**
 * Token de acceso emitido para el panel de monitorización.
 *
 * Transporta el token en formato JWT, su tipo y su vigencia en segundos.
 */
final class IssuedAccessToken
{
    public function __construct(
        private readonly string $accessToken,
        private readonly string $tokenType,
        private readonly int $expiresIn,
    ) {}

    /**
     * Token JWT que el cliente envía en la cabecera `Authorization`.
     */
    public function accessToken(): string
    {
        return $this->accessToken;
    }

    /**
     * Tipo de token, que se usa como prefijo de la cabecera `Authorization`.
     */
    public function tokenType(): string
    {
        return $this->tokenType;
    }

    /**
     * Vigencia del token en segundos desde su emisión.
     */
    public function expiresIn(): int
    {
        return $this->expiresIn;
    }
}
