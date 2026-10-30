<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Application\Operations\VerifyPrivatePerimeter;
use App\Domain\Operations\Service\PrivatePerimeterPolicy;
use App\Domain\Operations\ValueObject\PerimeterExposure;
use Tests\TestCase;

/**
 * Perímetro privado del despliegue: dentro del perímetro de MSD, en
 * On-Premise o en la nube privada, sólo el sondeo de disponibilidad y la
 * emisión del token pueden atender peticiones sin credencial (véase NFR012).
 */
class PrivatePerimeterTest extends TestCase
{
    public function test_it_exposes_only_the_health_probe_and_the_token_issuance(): void
    {
        $verdict = $this->app->make(VerifyPrivatePerimeter::class)->execute();

        $this->assertTrue(
            $verdict->isCompliant(),
            implode(' | ', $verdict->violations()),
        );
        $this->assertSame([], $verdict->violations());
    }

    public function test_it_publishes_every_data_route_with_the_token_requirement(): void
    {
        $exposures = $this->app->make(VerifyPrivatePerimeter::class)->exposures();

        $this->assertNotEmpty($exposures, 'El despliegue debe publicar las rutas de la API.');

        $public = array_values(array_filter(
            $exposures,
            static fn (PerimeterExposure $exposure): bool => $exposure->isPublic(),
        ));

        $uris = array_values(array_unique(array_map(
            static fn (PerimeterExposure $exposure): string => $exposure->uri(),
            $public,
        )));

        sort($uris);

        $this->assertSame(['api/v1/auth/token', 'api/v1/health'], $uris);
    }

    public function test_it_blocks_the_deployment_when_a_data_route_answers_without_a_token(): void
    {
        $verdict = (new PrivatePerimeterPolicy)->evaluate([
            PerimeterExposure::expose('GET', 'api/v1/batches', false),
        ]);

        $this->assertFalse($verdict->isCompliant());
        $this->assertCount(1, $verdict->violations());
        $this->assertStringContainsString('api/v1/batches', $verdict->violations()[0]);
    }

    public function test_it_admits_the_public_routes_of_the_perimeter(): void
    {
        $verdict = (new PrivatePerimeterPolicy)->evaluate([
            PerimeterExposure::expose('GET', 'api/v1/health', false),
            PerimeterExposure::expose('POST', 'api/v1/auth/token', false),
            PerimeterExposure::expose('GET', 'api/v1/dashboard/kpis', true),
        ]);

        $this->assertTrue($verdict->isCompliant());
        $this->assertSame([], $verdict->violations());
    }
}
