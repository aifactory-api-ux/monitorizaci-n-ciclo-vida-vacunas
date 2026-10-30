<?php

declare(strict_types=1);

namespace App\Domain\Authentication\Port;

use App\Domain\Authentication\ValueObject\IssuedAccessToken;
use App\Domain\Shared\Exception\InvalidCredentialsException;

/**
 * Puerto de emisión de tokens de acceso para el panel de monitorización.
 *
 * La implementación reside en la capa de infraestructura y usa Laravel
 * Passport para firmar el token en formato JWT (véase D9).
 */
interface AccessTokenIssuer
{
    /**
     * Emite un token de acceso para las credenciales indicadas.
     *
     * @throws InvalidCredentialsException Si las credenciales no son válidas.
     */
    public function issue(string $email, string $password): IssuedAccessToken;
}
