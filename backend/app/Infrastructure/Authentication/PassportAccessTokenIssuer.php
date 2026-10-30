<?php

declare(strict_types=1);

namespace App\Infrastructure\Authentication;

use App\Domain\Authentication\Port\AccessTokenIssuer;
use App\Domain\Authentication\ValueObject\IssuedAccessToken;
use App\Domain\Shared\Exception\ConfigurationException;
use App\Domain\Shared\Exception\InvalidCredentialsException;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;

/**
 * Emisor de tokens de acceso basado en el password grant de Laravel Passport.
 *
 * Delega la emisión en el servidor de autorización de Passport, que firma
 * el token en formato JWT con la clave privada del despliegue (véase D9).
 */
final class PassportAccessTokenIssuer implements AccessTokenIssuer
{
    public function __construct(
        private readonly AuthorizationServer $server,
    ) {}

    /**
     * Emite un token de acceso para las credenciales indicadas.
     *
     * @throws InvalidCredentialsException Si las credenciales no son válidas.
     * @throws ConfigurationException Si no hay ningún cliente de contraseña activo.
     */
    public function issue(string $email, string $password): IssuedAccessToken
    {
        $client = $this->passwordClient();

        $request = (new PsrHttpFactory)->createRequest(Request::create(config('app.url'), 'POST', [
            'grant_type' => 'password',
            'client_id' => (string) $client->getKey(),
            'username' => $email,
            'password' => $password,
        ]));

        try {
            $response = $this->server->respondToAccessTokenRequest($request, app(ResponseInterface::class));
        } catch (OAuthServerException) {
            throw InvalidCredentialsException::rejected();
        }

        $payload = json_decode((string) $response->getBody(), true);

        if (! is_array($payload) || ! isset($payload['access_token']) || ! is_string($payload['access_token'])) {
            throw InvalidCredentialsException::rejected();
        }

        return new IssuedAccessToken(
            $payload['access_token'],
            is_string($payload['token_type'] ?? null) ? $payload['token_type'] : 'Bearer',
            (int) ($payload['expires_in'] ?? 0),
        );
    }

    /**
     * Cliente de contraseña activo con el que se firma el token.
     *
     * @throws ConfigurationException Si no hay ningún cliente de contraseña.
     */
    private function passwordClient(): Client
    {
        $clients = Passport::client()
            ->newQuery()
            ->where('revoked', false)
            ->get();

        foreach ($clients as $client) {
            if ($client instanceof Client && $client->hasGrantType('password')) {
                return $client;
            }
        }

        throw ConfigurationException::missingPasswordClient();
    }
}
