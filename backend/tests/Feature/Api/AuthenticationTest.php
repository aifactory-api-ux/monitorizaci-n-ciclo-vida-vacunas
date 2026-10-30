<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\User\ValueObject\UserRole;
use App\Infrastructure\Persistence\Eloquent\Model\AppUserModel;
use Database\Seeders\PassportClientSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Tests\Support\InteractsWithDatabase;
use Tests\TestCase;

/**
 * Emisión de tokens JWT y protección de las rutas de datos.
 *
 * El panel se autentica con un token Bearer emitido por Passport; sin un
 * token válido la respuesta es 401 (véase D9, TC007 y NFR003).
 */
class AuthenticationTest extends TestCase
{
    use InteractsWithDatabase;
    use RefreshDatabase;

    public function test_it_issues_a_bearer_token_for_a_seeded_user(): void
    {
        $this->seed(PassportClientSeeder::class);
        $user = $this->createUser(UserRole::OPERATOR);

        $response = $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'clave-de-prueba',
        ]);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['token_type', 'access_token', 'expires_in']);

        $this->assertSame(3, count(explode('.', (string) $response->json('access_token'))));
    }

    public function test_it_rejects_the_issuance_with_wrong_credentials(): void
    {
        $this->seed(PassportClientSeeder::class);
        $user = $this->createUser(UserRole::OPERATOR);

        $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'clave-incorrecta',
        ])->assertStatus(401)
            ->assertJsonPath('error_code', 'invalid_credentials');
    }

    public function test_data_routes_reject_requests_without_an_authorization_header(): void
    {
        $this->getJson('/api/v1/batches')->assertUnauthorized();
    }

    public function test_data_routes_reject_requests_with_an_invalid_token(): void
    {
        $this->getJson('/api/v1/batches', [
            'Authorization' => 'Bearer token-invalido',
        ])->assertUnauthorized();
    }

    public function test_data_routes_reject_requests_with_an_expired_token(): void
    {
        $this->seed(PassportClientSeeder::class);
        $user = $this->createUser(UserRole::OPERATOR);

        $this->withHeader('Authorization', 'Bearer '.$this->expiredToken($user))
            ->getJson('/api/v1/batches')
            ->assertUnauthorized();
    }

    public function test_data_routes_accept_requests_with_a_valid_token(): void
    {
        $this->seed(PassportClientSeeder::class);
        $user = $this->createUser(UserRole::OPERATOR);

        $this->withHeader('Authorization', 'Bearer '.$this->issueToken($user))
            ->getJson('/api/v1/batches')
            ->assertOk()
            ->assertJsonStructure(['batches']);
    }

    public function test_the_health_route_remains_public(): void
    {
        $this->getJson('/api/v1/health')->assertOk();
    }

    /**
     * Emite un token Bearer para el usuario indicado.
     */
    private function issueToken(AppUserModel $user): string
    {
        $response = $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'clave-de-prueba',
        ]);

        $response->assertOk();

        return (string) $response->json('access_token');
    }

    /**
     * Token firmado con la clave de la aplicación y con la vigencia ya
     * cumplida, para comprobar el rechazo de los tokens caducados.
     */
    private function expiredToken(AppUserModel $user): string
    {
        $configuration = Configuration::forAsymmetricSigner(
            new Sha256,
            InMemory::file(Passport::keyPath('oauth-private.key')),
            InMemory::file(Passport::keyPath('oauth-public.key')),
        );

        $clientId = (string) Passport::client()->newQuery()->where('revoked', false)->first()?->getKey();
        $issuedAt = new \DateTimeImmutable('-1 hour');

        $token = $configuration->builder()
            ->issuedBy($clientId)
            ->permittedFor($clientId)
            ->identifiedBy((string) Str::uuid())
            ->issuedAt($issuedAt)
            ->expiresAt($issuedAt->modify('+30 minutes'))
            ->relatedTo((string) $user->getAuthIdentifier())
            ->withClaim('scopes', [])
            ->getToken($configuration->signer(), $configuration->signingKey());

        return $token->toString();
    }
}
