<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_is_public_and_reports_the_database_status(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'service',
                'environment',
                'timestamp',
                'checks' => [
                    'database' => [
                        'status',
                        'driver',
                    ],
                ],
            ])
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database.status', 'ok');
    }

    public function test_health_endpoint_reports_the_state_of_every_configured_dependency(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk();

        $checks = $response->json('checks');

        $this->assertIsArray($checks);
        $this->assertArrayHasKey('database', $checks);

        foreach ($checks as $dependency => $result) {
            $this->assertSame(
                'ok',
                $result['status'] ?? null,
                sprintf('La dependencia "%s" no responde correctamente.', $dependency)
            );
        }
    }
}
