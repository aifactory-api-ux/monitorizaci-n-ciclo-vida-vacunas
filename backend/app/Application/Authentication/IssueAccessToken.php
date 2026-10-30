<?php

declare(strict_types=1);

namespace App\Application\Authentication;

use App\Domain\Authentication\Port\AccessTokenIssuer;
use App\Domain\Authentication\ValueObject\IssuedAccessToken;
use App\Domain\Shared\Exception\InvalidCredentialsException;

/**
 * Caso de uso de emisión del token de acceso del panel de monitorización.
 *
 * Valida las credenciales del usuario y entrega el token JWT que el cliente
 * enviará en la cabecera `Authorization` de las peticiones de datos (véase D9).
 */
final class IssueAccessToken
{
    public function __construct(
        private readonly AccessTokenIssuer $issuer,
    ) {}

    /**
     * Emite el token de acceso de las credenciales indicadas.
     *
     * @throws InvalidCredentialsException Si las credenciales no son válidas.
     */
    public function execute(string $email, string $password): IssuedAccessToken
    {
        return $this->issuer->issue($email, $password);
    }
}
