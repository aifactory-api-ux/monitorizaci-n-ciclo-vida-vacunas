<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\VaccineBatch\Entity\Campus;
use App\Domain\VaccineBatch\Repository\PhaseTransitionRepository;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;
use App\Domain\VaccineBatch\ValueObject\BatchStatus;
use App\Infrastructure\Persistence\Eloquent\Repository\EloquentPhaseTransitionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use ReflectionMethod;
use Tests\Support\InteractsWithDatabase;
use Tests\Support\MakesAuthenticatedRequests;
use Tests\TestCase;

/**
 * Avance secuencial de fase y registro inmutable de la transición
 * (véase TC012 y TC022).
 */
class BatchPhaseAdvanceTest extends TestCase
{
    use InteractsWithDatabase;
    use MakesAuthenticatedRequests;
    use RefreshDatabase;

    public function test_it_advances_the_batch_to_the_sphereon_phase_and_relocates_it(): void
    {
        $batchId = $this->createBatch();

        $response = $this->postJson("/api/v1/batches/{$batchId}/transitions", [
            'to_phase' => BatchPhase::SPHEREON,
        ]);

        $response->assertOk()
            ->assertJsonPath('batch.phase', BatchPhase::SPHEREON)
            ->assertJsonPath('batch.status', BatchStatus::IN_PROGRESS)
            ->assertJsonPath('batch.current_campus_id', $this->campusId(Campus::SPHEREON))
            ->assertJsonPath('transition.from_phase', BatchPhase::CULTURE)
            ->assertJsonPath('transition.to_phase', BatchPhase::SPHEREON);

        $this->assertDatabaseHas('VACCINE_BATCH', [
            'id' => $batchId,
            'phase' => BatchPhase::SPHEREON,
            'status' => BatchStatus::IN_PROGRESS,
            'current_campus_id' => $this->campusId(Campus::SPHEREON),
        ]);
    }

    public function test_each_advance_records_the_transition_with_its_date(): void
    {
        $batchId = $this->createBatch();

        $this->postJson("/api/v1/batches/{$batchId}/transitions", [
            'to_phase' => BatchPhase::SPHEREON,
        ])->assertOk();

        $this->assertDatabaseHas('PHASE_TRANSITION', [
            'batch_id' => $batchId,
            'from_phase' => BatchPhase::CULTURE,
            'to_phase' => BatchPhase::SPHEREON,
        ]);

        $this->assertDatabaseCount('PHASE_TRANSITION', 1);

        $transition = $this->app->make(PhaseTransitionRepository::class)->findByBatchId($batchId);

        $this->assertCount(1, $transition);
        $this->assertNotNull($transition[0]->transitionDate());
    }

    public function test_the_transition_record_has_no_update_or_delete_path(): void
    {
        $port = new ReflectionClass(PhaseTransitionRepository::class);
        $adapter = new ReflectionClass(EloquentPhaseTransitionRepository::class);

        $this->assertSame(
            ['append', 'findAll', 'findByBatchId'],
            $this->sortedMethodNames($port),
            'El puerto de transiciones sólo admite el alta y la consulta.',
        );

        $this->assertSame(
            ['append', 'findAll', 'findByBatchId'],
            $this->sortedMethodNames($adapter),
            'El adaptador de transiciones no expone actualización ni borrado.',
        );
    }

    public function test_it_rejects_a_non_sequential_advance_without_mutating_the_batch(): void
    {
        $batchId = $this->createBatch();
        $campusOne = $this->campusId(Campus::CULTURE);

        $response = $this->postJson("/api/v1/batches/{$batchId}/transitions", [
            'to_phase' => BatchPhase::LOGISTICS,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'non_sequential_transition');

        $this->assertDatabaseHas('VACCINE_BATCH', [
            'id' => $batchId,
            'phase' => BatchPhase::CULTURE,
            'status' => BatchStatus::IN_PROGRESS,
            'current_campus_id' => $campusOne,
        ]);

        $this->assertDatabaseCount('PHASE_TRANSITION', 0);
    }

    public function test_it_rejects_a_rollback_without_mutating_the_batch(): void
    {
        $batchId = $this->createBatch();

        $this->postJson("/api/v1/batches/{$batchId}/transitions", [
            'to_phase' => BatchPhase::SPHEREON,
        ])->assertOk();

        $response = $this->postJson("/api/v1/batches/{$batchId}/transitions", [
            'to_phase' => BatchPhase::CULTURE,
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('VACCINE_BATCH', [
            'id' => $batchId,
            'phase' => BatchPhase::SPHEREON,
            'current_campus_id' => $this->campusId(Campus::SPHEREON),
        ]);

        $this->assertDatabaseCount('PHASE_TRANSITION', 1);
    }

    public function test_it_rejects_the_repetition_of_a_phase(): void
    {
        $batchId = $this->createBatch();

        $this->postJson("/api/v1/batches/{$batchId}/transitions", [
            'to_phase' => BatchPhase::CULTURE,
        ])->assertStatus(422);

        $this->assertDatabaseHas('VACCINE_BATCH', [
            'id' => $batchId,
            'phase' => BatchPhase::CULTURE,
        ]);

        $this->assertDatabaseCount('PHASE_TRANSITION', 0);
    }

    public function test_it_rejects_an_unknown_phase_literal(): void
    {
        $batchId = $this->createBatch();

        $this->postJson("/api/v1/batches/{$batchId}/transitions", [
            'to_phase' => 'Fase inexistente',
        ])->assertStatus(422)->assertJsonValidationErrors('to_phase');

        $this->assertDatabaseCount('PHASE_TRANSITION', 0);
    }

    public function test_it_returns_not_found_for_an_unknown_batch(): void
    {
        $this->seedCampuses();
        $this->actingAsUser('Operador de Producción');

        $this->postJson('/api/v1/batches/99999999-9999-4999-8999-999999999999/transitions', [
            'to_phase' => BatchPhase::SPHEREON,
        ])->assertStatus(404)->assertJsonPath('error_code', 'batch_not_found');
    }

    /**
     * Crea un lote en la fase de cultivo y devuelve su identificador.
     */
    private function createBatch(): string
    {
        $this->seedCampuses();
        $this->seedVaccineReferences();
        $this->actingAsUser('Operador de Producción');

        $response = $this->postJson('/api/v1/batches', [
            'reference_id' => $this->vaccineReferenceId('Vacuna conjugada intramuscular'),
        ]);

        $response->assertCreated();

        return (string) $response->json('id');
    }

    /**
     * Nombres de los métodos públicos de una clase, ordenados.
     *
     * @return list<string>
     */
    private function sortedMethodNames(ReflectionClass $class): array
    {
        $names = array_map(
            fn (ReflectionMethod $method): string => $method->getName(),
            $class->getMethods(ReflectionMethod::IS_PUBLIC),
        );

        sort($names);

        return $names;
    }
}
