<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Application\Batches\AdvanceBatchPhase;
use App\Domain\VaccineBatch\Entity\VaccineBatch;
use App\Domain\VaccineBatch\Repository\VaccineBatchRepository;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;
use App\Domain\VaccineBatch\ValueObject\BatchStatus;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithDatabase;
use Tests\Support\MakesAuthenticatedRequests;
use Tests\TestCase;

/**
 * Indicadores del panel: tiempos de ciclo por fase y desglose de estados
 * (véase D21 y TC021).
 */
class DashboardKpisTest extends TestCase
{
    use InteractsWithDatabase;
    use MakesAuthenticatedRequests;
    use RefreshDatabase;

    /**
     * Fecha de alta de los lotes de las pruebas.
     */
    private const CREATED_AT = '2026-01-01 00:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCampuses();
        $this->seedVaccineReferences();
    }

    public function test_it_returns_the_cycle_times_derived_from_the_recorded_transitions(): void
    {
        $this->actingAsUser('Supervisor de Planta');

        $first = $this->createBatch('Vacuna conjugada intramuscular');
        $second = $this->createBatch('Vacuna combinada trivalente');

        $this->advancePhase($first, BatchPhase::SPHEREON, '2026-01-03 00:00:00');
        $this->advancePhase($second, BatchPhase::SPHEREON, '2026-01-02 12:00:00');

        $response = $this->getJson('/api/v1/dashboard/kpis')->assertOk();

        $response->assertJsonPath('kpis.cycle_time.total_batches', 2)
            ->assertJsonCount(3, 'kpis.cycle_time.by_phase');

        $byPhase = collect($response->json('kpis.cycle_time.by_phase'))->keyBy('phase');

        $this->assertSame(151200.0, (float) $byPhase->get(BatchPhase::CULTURE)['average_seconds']);
        $this->assertSame(42.0, (float) $byPhase->get(BatchPhase::CULTURE)['average_hours']);
        $this->assertSame(2, $byPhase->get(BatchPhase::CULTURE)['sample_size']);
        $this->assertSame(0, $byPhase->get(BatchPhase::CULTURE)['in_progress_count']);

        $this->assertNull($byPhase->get(BatchPhase::SPHEREON)['average_seconds']);
        $this->assertSame(0, $byPhase->get(BatchPhase::SPHEREON)['sample_size']);
        $this->assertSame(2, $byPhase->get(BatchPhase::SPHEREON)['in_progress_count']);

        $this->assertNull($byPhase->get(BatchPhase::LOGISTICS)['average_seconds']);
        $this->assertSame(0, $byPhase->get(BatchPhase::LOGISTICS)['in_progress_count']);
    }

    public function test_it_returns_the_status_breakdown_of_the_registered_batches(): void
    {
        $this->actingAsUser('Supervisor de Planta');

        $this->createBatch('Vacuna oral bivalente');
        $blocked = $this->createBatch('Vacuna combinada trivalente');

        $this->advancePhase($blocked, BatchPhase::SPHEREON, '2026-01-02 00:00:00');

        $this->postJson("/api/v1/batches/{$blocked}/transitions", [
            'to_phase' => BatchPhase::LOGISTICS,
        ])->assertStatus(422)->assertJsonPath('error_code', 'gating_not_approved');

        $response = $this->getJson('/api/v1/dashboard/kpis')->assertOk();

        $response->assertJsonPath('kpis.cycle_time.total_batches', 2)
            ->assertJsonCount(2, 'kpis.status_breakdown');

        $breakdown = collect($response->json('kpis.status_breakdown'))->keyBy('status');

        $this->assertSame(1, $breakdown->get(BatchStatus::IN_PROGRESS)['count']);
        $this->assertSame(50.0, (float) $breakdown->get(BatchStatus::IN_PROGRESS)['percentage']);
        $this->assertSame(1, $breakdown->get(BatchStatus::BLOCKED_BY_GATING)['count']);
        $this->assertSame(50.0, (float) $breakdown->get(BatchStatus::BLOCKED_BY_GATING)['percentage']);
    }

    public function test_it_returns_the_kpis_of_a_plant_without_batches(): void
    {
        $this->actingAsUser('Supervisor de Planta');

        $response = $this->getJson('/api/v1/dashboard/kpis')->assertOk();

        $response->assertJsonPath('kpis.cycle_time.total_batches', 0)
            ->assertJsonCount(3, 'kpis.cycle_time.by_phase')
            ->assertJsonCount(0, 'kpis.status_breakdown');

        foreach ($response->json('kpis.cycle_time.by_phase') as $cycleTime) {
            $this->assertNull($cycleTime['average_seconds']);
            $this->assertSame(0, $cycleTime['sample_size']);
            $this->assertSame(0, $cycleTime['in_progress_count']);
        }
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/v1/dashboard/kpis')->assertStatus(401);
    }

    /**
     * Crea un lote con la fecha de alta de las pruebas y devuelve su identificador.
     */
    private function createBatch(string $referenceName): string
    {
        $batch = VaccineBatch::create(
            $this->vaccineReferenceId($referenceName),
            $this->cultureCampusId(),
            new DateTimeImmutable(self::CREATED_AT),
        );

        $this->app->make(VaccineBatchRepository::class)->save($batch);

        return $batch->id();
    }

    /**
     * Avanza el lote a la fase indicada en la fecha indicada.
     */
    private function advancePhase(string $batchId, string $toPhase, string $at): void
    {
        $this->app->make(AdvanceBatchPhase::class)->execute(
            $batchId,
            BatchPhase::from($toPhase),
            null,
            new DateTimeImmutable($at),
        );
    }
}
