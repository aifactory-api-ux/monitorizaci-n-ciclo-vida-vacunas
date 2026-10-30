<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Application\Batches\RegisterPhysicalReception;
use App\Domain\VaccineBatch\Entity\Campus;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;
use App\Domain\VaccineBatch\ValueObject\BatchStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithDatabase;
use Tests\Support\InteractsWithLims;
use Tests\Support\MakesAuthenticatedRequests;
use Tests\TestCase;

/**
 * Recepción física y cierre del ciclo logístico (véase TC017).
 */
class LogisticsCycleTest extends TestCase
{
    use InteractsWithDatabase;
    use InteractsWithLims;
    use MakesAuthenticatedRequests;
    use RefreshDatabase;

    public function test_it_registers_the_physical_reception_and_moves_the_batch_to_received(): void
    {
        $batchId = $this->createBatchInLogisticsPhase();

        $response = $this->postJson("/api/v1/batches/{$batchId}/reception");

        $response->assertOk()
            ->assertJsonPath('status', BatchStatus::RECEIVED)
            ->assertJsonPath('phase', BatchPhase::LOGISTICS)
            ->assertJsonPath('current_campus_id', $this->campusId(Campus::LOGISTICS));

        $this->assertDatabaseHas('VACCINE_BATCH', [
            'id' => $batchId,
            'status' => BatchStatus::RECEIVED,
        ]);

        $this->assertDatabaseHas('LOG_ENTRY', [
            'batch_id' => $batchId,
            'event_type' => RegisterPhysicalReception::EVENT_TYPE,
        ]);
    }

    public function test_it_closes_the_logistics_cycle_and_keeps_the_campus(): void
    {
        $batchId = $this->createBatchInLogisticsPhase();
        $campusThree = $this->campusId(Campus::LOGISTICS);

        $this->postJson("/api/v1/batches/{$batchId}/reception")->assertOk();

        $response = $this->postJson("/api/v1/batches/{$batchId}/logistics-close");

        $response->assertOk()
            ->assertJsonPath('status', BatchStatus::CLOSED)
            ->assertJsonPath('current_campus_id', $campusThree);

        $this->assertDatabaseHas('VACCINE_BATCH', [
            'id' => $batchId,
            'status' => BatchStatus::CLOSED,
            'current_campus_id' => $campusThree,
        ]);
    }

    public function test_it_rejects_the_closure_of_a_batch_that_was_not_received(): void
    {
        $batchId = $this->createBatchInLogisticsPhase();

        $response = $this->postJson("/api/v1/batches/{$batchId}/logistics-close");

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'invalid_logistics_closure');

        $this->assertDatabaseHas('VACCINE_BATCH', [
            'id' => $batchId,
            'status' => BatchStatus::IN_PROGRESS,
        ]);
    }

    public function test_it_rejects_the_physical_reception_outside_the_logistics_phase(): void
    {
        $this->seedCampuses();
        $this->seedVaccineReferences();
        $this->actingAsUser('Operador de Producción');

        $response = $this->postJson('/api/v1/batches', [
            'reference_id' => $this->vaccineReferenceId('Vacuna conjugada intramuscular'),
        ]);

        $batchId = (string) $response->json('id');

        $this->postJson("/api/v1/batches/{$batchId}/reception")
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'invalid_reception_phase');

        $this->assertDatabaseHas('VACCINE_BATCH', [
            'id' => $batchId,
            'status' => BatchStatus::IN_PROGRESS,
        ]);
    }

    public function test_it_returns_not_found_for_an_unknown_batch(): void
    {
        $this->seedCampuses();
        $this->actingAsUser('Coordinador de Logística');

        $this->postJson('/api/v1/batches/99999999-9999-4999-8999-999999999999/reception')
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'batch_not_found');
    }

    /**
     * Crea un lote ya situado en la fase logística del Campus 3.
     */
    private function createBatchInLogisticsPhase(): string
    {
        $this->seedCampuses();
        $this->seedVaccineReferences();
        $this->actingAsUser('Operador de Producción');

        $response = $this->postJson('/api/v1/batches', [
            'reference_id' => $this->vaccineReferenceId('Vacuna conjugada intramuscular'),
        ]);

        $batchId = (string) $response->json('id');
        $referenceId = (string) $response->json('reference_id');

        $this->postJson("/api/v1/batches/{$batchId}/transitions", [
            'to_phase' => BatchPhase::SPHEREON,
        ])->assertOk();

        // La fase logística exige la aprobación microbiológica de LIMS Labware.
        $this->importLimsApproval($batchId, $referenceId);

        $this->postJson("/api/v1/batches/{$batchId}/transitions", [
            'to_phase' => BatchPhase::LOGISTICS,
        ])->assertOk();

        return $batchId;
    }
}
