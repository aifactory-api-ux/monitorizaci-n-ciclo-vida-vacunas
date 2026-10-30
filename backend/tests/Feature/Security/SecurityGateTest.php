<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Domain\Security\Port\SecurityStageScanner;
use App\Domain\Security\Service\SecurityGate;
use App\Domain\Security\ValueObject\SecurityStageResult;
use App\Infrastructure\Security\ProcessSecurityStageScanner;
use Illuminate\Console\Command;
use Tests\Doubles\FakeSecurityStageScanner;
use Tests\TestCase;

/**
 * Etapa de análisis de seguridad que bloquea la promoción del código
 * cuando detecta vulnerabilidades (véase D20 y NFR011).
 */
class SecurityGateTest extends TestCase
{
    public function test_it_allows_the_promotion_when_every_stage_has_passed(): void
    {
        $verdict = (new SecurityGate)->evaluate([
            SecurityStageResult::passed('security-scan', 'No se han detectado vulnerabilidades.'),
            SecurityStageResult::passed('secret-scan', 'No se han versionado credenciales.'),
        ]);

        $this->assertTrue($verdict->allowsPromotion());
        $this->assertSame([], $verdict->blockedStages());
    }

    public function test_it_blocks_the_promotion_when_a_stage_detects_vulnerabilities(): void
    {
        $verdict = (new SecurityGate)->evaluate([
            SecurityStageResult::passed('security-scan'),
            SecurityStageResult::failed(
                'security-scan',
                'Se han detectado vulnerabilidades.',
                ['CVE-2026-1234'],
            ),
        ]);

        $this->assertFalse($verdict->allowsPromotion());
        $this->assertSame(['security-scan'], $verdict->blockedStages());
    }

    public function test_it_blocks_the_promotion_when_no_stage_has_been_executed(): void
    {
        $verdict = (new SecurityGate)->evaluate([]);

        $this->assertFalse($verdict->allowsPromotion());
    }

    public function test_the_command_blocks_the_promotion_when_the_analysis_detects_vulnerabilities(): void
    {
        $this->app->instance('security-stage-scanners', [
            new FakeSecurityStageScanner(SecurityStageResult::failed(
                'security-scan',
                'Se han detectado vulnerabilidades.',
                ['CVE-2026-1234'],
            )),
        ]);

        $this->artisan('security:gate')
            ->expectsOutputToContain('security-scan')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_the_command_allows_the_promotion_when_the_analysis_has_passed(): void
    {
        $this->app->instance('security-stage-scanners', [
            new FakeSecurityStageScanner(SecurityStageResult::passed('security-scan')),
        ]);

        $this->artisan('security:gate')->assertExitCode(Command::SUCCESS);
    }

    public function test_the_command_blocks_the_promotion_when_the_stage_has_no_command_configured(): void
    {
        $this->app->instance('security-stage-scanners', [
            new ProcessSecurityStageScanner('security-scan', ''),
        ]);

        $this->artisan('security:gate')->assertExitCode(Command::FAILURE);
    }

    public function test_the_analyzer_reports_the_vulnerabilities_detected_by_the_command(): void
    {
        $scanner = new ProcessSecurityStageScanner(
            'security-scan',
            'printf "CVE-2026-1234 vulnerability found"; exit 1',
        );

        $result = $scanner->scan();

        $this->assertFalse($result->hasPassed());
        $this->assertSame(['CVE-2026-1234'], $result->findings());
    }

    public function test_the_analyzer_accepts_a_command_that_finds_no_vulnerabilities(): void
    {
        $scanner = new ProcessSecurityStageScanner('security-scan', 'printf "No vulnerabilities found"');

        $result = $scanner->scan();

        $this->assertTrue($result->hasPassed());
        $this->assertSame([], $result->findings());
    }

    public function test_the_analyzer_reports_a_stage_without_command_as_failed(): void
    {
        $result = (new ProcessSecurityStageScanner('security-scan', ''))->scan();

        $this->assertFalse($result->hasPassed());
        $this->assertSame('security-scan', $result->stage());
    }

    public function test_the_gate_is_applied_to_the_stages_declared_by_the_deployment(): void
    {
        $stages = $this->app->make('security-stage-scanners');

        $this->assertNotEmpty($stages, 'El despliegue debe declarar al menos una etapa de seguridad.');

        foreach ($stages as $stage) {
            $this->assertInstanceOf(SecurityStageScanner::class, $stage);
        }
    }
}
