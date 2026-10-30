<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Application\Lims\ForceGatingRelease;
use App\Domain\VaccineBatch\Entity\Campus;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;
use App\Domain\VaccineBatch\ValueObject\BatchStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithDatabase;
use Tests\Support\InteractsWithLims;
use Tests\Support\MakesAuthenticatedRequests;
use Tests\TestCase;

/**
 * Gating microbiológico en el avance de fase y forzaje de la liberación
 * (véase TC009 y TC016).
 */
class GatingTest extends TestCase
{
    use InteractsWithDatabase;
    use InteractsWithLims;
    use MakesAuthenticatedRequests;
    use RefreshDatabase;

    public function test_it_blocks_the_advance_to_the_logistics_phase_without_microbiology_approval(): void
    {
        [$batchId] = $this->createBatch();

        $this->postJson("/api/v1/batches/{$batchId}/transitions", [
            'to_phase' => BatchPhase::SPHEREON,
        ])->assertOk();

        $response = $this->postJson("/api/v1/batches/{$batchId}/transitions", [
            'to_phase' => BatchPhase::LOGISTICS,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'gating_not_approved');

        $this->assertDatabaseHas('VACCINE_BATCH', [
            'id' => $batchId,
            'phase' => BatchPhase::SPHEREON,
            'status' => BatchStatus::BLOCKED_BY_GATING,
            'current_campus_id' => $this->campusId(Campus::SPHEREON),
        ]);
    }

    public function test_it_advances_to_the_logistics_phase_with_the_microbiology_approval(): void
    {
        [$batchId, $referenceId] = $this->createBatch();

        $this->postJson("/api/v1/batches/{$batchId}/transitions", [
            'to_phase' => BatchPhase::SPHEREON,
        ])->assertOk();

        $this->importLimsApproval($batchId, $referenceId);

        $response = $this->postJson("/api/v1/batches/{$batchId}/transitions", [
            'to_phase' => BatchPhase::LOGISTICS,
        ]);

        $response->assertOk()
            ->assertJsonPath('batch.phase', BatchPhase::LOGISTICS)
            ->assertJsonPath('batch.status', BatchStatus::IN_PROGRESS)
            ->assertJsonPath('batch.current_campus_id', $this->campusId(Campus::LOGISTICS));
    }

    public function test_it_exposes_the_blocked_gating_state_in_the_batch_detail(): void
    {
        [$batchId] = $this->createBatch();

        $this->postJson("/api/v1/batches/{$batchId}/transitions", [
            'to_phase' => BatchPhase::SPHEREON,
        ])->assertOk();

        $this->postJson("/api/v1/batches/{$batchId}/transitions", [
            'to_phase' => BatchPhase::LOGISTICS,
        ])->assertStatus(422);

        // El lote ya está fuera del alcance de visibilidad del Operador de
        // Producción, por lo que el detalle lo consulta un rol con
        // visibilidad completa sobre todas las fases.
        $this->actingAsUser('Supervisor de Planta');

        $this->getJson("/api/v1/batches/{$batchId}")
            ->assertOk()
            ->assertJsonPath('batch.status', BatchStatus::BLOCKED_BY_GATING)
            ->assertJsonPath('gating.approved', false);

        $this->getJson("/api/v1/batches/{$batchId}/gating")
            ->assertOk()
            ->assertJsonPath('gating.approved', false);
    }

    public function test_it_denies_the_forced_release_of_the_batch(): void
    {
        [$batchId] = $this->createBatch();

        $this->actingAsUser('Técnico de Control de Calidad');

        $response = $this->postJson("/api/v1/batches/{$batchId}/gating/release");

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'gating_release_forbidden');

        $this->assertStringContainsString(
            'prohibido forzar la liberación',
            (string) $response->json('message'),
        );

        $this->assertDatabaseHas('VACCINE_BATCH', [
            'id' => $batchId,
            'phase' => BatchPhase::CULTURE,
            'status' => BatchStatus::IN_PROGRESS,
            'current_campus_id' => $this->campusId(Campus::CULTURE),
        ]);

        $this->assertDatabaseHas('LOG_ENTRY', [
            'batch_id' => $batchId,
            'event_type' => ForceGatingRelease::EVENT_TYPE_DENIED,
        ]);
    }

    /**
     * Crea un lote en la fase de cultivo y devuelve su identificador y la
     * referencia de vacuna asociada.
     *
     * @return array{0: string, 1: string}
     */
    private function createBatch(): array
    {
        $this->seedCampuses();
        $this->seedVaccineReferences();
        $this->actingAsUser('Operador de Producción');

        $response = $this->postJson('/api/v1/batches', [
            'reference_id' => $this->vaccineReferenceId('Vacuna conjugada intramuscular'),
        ]);

        $response->assertCreated();

        return [(string) $response->json('id'), (string) $response->json('reference_id')];
    }
}
