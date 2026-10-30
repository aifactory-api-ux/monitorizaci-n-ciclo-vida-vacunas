<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Lims\Entity\LimsResult;
use App\Domain\VaccineBatch\Entity\Campus;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithDatabase;
use Tests\Support\InteractsWithLims;
use Tests\Support\MakesAuthenticatedRequests;
use Tests\TestCase;

/**
 * Autorización por rol y acotamiento de la visibilidad de los lotes
 * (véase TC018, TC021, TC027 y D9).
 */
class AuthorizationTest extends TestCase
{
    use InteractsWithDatabase;
    use InteractsWithLims;
    use MakesAuthenticatedRequests;
    use RefreshDatabase;

    public function test_it_denies_the_logistics_coordinator_to_modify_the_production_data(): void
    {
        $batchId = $this->createBatch();

        $this->actingAsUser('Coordinador de Logística');

        $this->postJson('/api/v1/batches', [
            'reference_id' => $this->vaccineReferenceId('Vacuna conjugada intramuscular'),
        ])->assertStatus(403)
            ->assertJsonPath('error_code', 'operation_forbidden');

        $this->postJson("/api/v1/batches/{$batchId}/transitions", [
            'to_phase' => BatchPhase::SPHEREON,
        ])->assertStatus(403)
            ->assertJsonPath('error_code', 'operation_forbidden');
    }

    public function test_it_denies_the_logistics_coordinator_to_modify_the_quality_data(): void
    {
        $batchId = $this->createBatch();

        $this->actingAsUser('Coordinador de Logística');

        $this->postJson('/api/v1/lims/import')->assertStatus(403)
            ->assertJsonPath('error_code', 'operation_forbidden');

        $this->postJson("/api/v1/batches/{$batchId}/lims-results", $this->limsResultPayload())
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'operation_forbidden');

        $this->postJson("/api/v1/batches/{$batchId}/gating/release")->assertStatus(403)
            ->assertJsonPath('error_code', 'operation_forbidden');
    }

    public function test_it_allows_the_logistics_coordinator_to_manage_the_logistics_cycle(): void
    {
        $batchId = $this->createBatchInLogisticsPhase();

        $this->actingAsUser('Coordinador de Logística');

        $this->postJson("/api/v1/batches/{$batchId}/reception")->assertOk();

        $this->postJson("/api/v1/batches/{$batchId}/logistics-close")->assertOk();
    }

    public function test_it_denies_the_auditor_to_alter_the_pharmaceutical_records(): void
    {
        $batchId = $this->createBatch();

        $this->actingAsUser('Auditor / Administrador IT');

        $this->postJson("/api/v1/batches/{$batchId}/lims-results", $this->limsResultPayload())
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'operation_forbidden');

        $this->postJson("/api/v1/batches/{$batchId}/gating/release")->assertStatus(403)
            ->assertJsonPath('error_code', 'operation_forbidden');
    }

    public function test_it_denies_the_auditor_to_alter_the_operational_data(): void
    {
        $batchId = $this->createBatch();

        $this->actingAsUser('Auditor / Administrador IT');

        $this->postJson('/api/v1/batches', [
            'reference_id' => $this->vaccineReferenceId('Vacuna conjugada intramuscular'),
        ])->assertStatus(403)
            ->assertJsonPath('error_code', 'operation_forbidden');

        $this->postJson("/api/v1/batches/{$batchId}/transitions", [
            'to_phase' => BatchPhase::SPHEREON,
        ])->assertStatus(403)
            ->assertJsonPath('error_code', 'operation_forbidden');

        $this->postJson("/api/v1/batches/{$batchId}/reception")->assertStatus(403)
            ->assertJsonPath('error_code', 'operation_forbidden');

        $this->postJson("/api/v1/batches/{$batchId}/logistics-close")->assertStatus(403)
            ->assertJsonPath('error_code', 'operation_forbidden');
    }

    public function test_it_allows_the_quality_control_to_register_the_microbiology_result(): void
    {
        $batchId = $this->createBatch();

        $this->actingAsUser('Técnico de Control de Calidad');

        $response = $this->postJson("/api/v1/batches/{$batchId}/lims-results", $this->limsResultPayload());

        $response->assertCreated()
            ->assertJsonPath('batch_id', $batchId)
            ->assertJsonPath('test_type', LimsResult::TEST_TYPE_MICROBIOLOGY)
            ->assertJsonPath('result', LimsResult::RESULT_APPROVED)
            ->assertJsonPath('source', LimsResult::SOURCE_QUALITY_CONTROL);
    }

    public function test_it_limits_the_production_operator_to_the_cultivation_phase(): void
    {
        $cultureBatchId = $this->createBatch();
        $sphereonBatchId = $this->createBatchInPhase(BatchPhase::SPHEREON);
        $logisticsBatchId = $this->createBatchInPhase(BatchPhase::LOGISTICS);

        $this->actingAsUser('Operador de Producción');

        $response = $this->getJson('/api/v1/batches')->assertOk();

        $listedIds = array_column($response->json('batches'), 'id');

        $this->assertContains($cultureBatchId, $listedIds);
        $this->assertNotContains($sphereonBatchId, $listedIds);
        $this->assertNotContains($logisticsBatchId, $listedIds);

        $this->getJson("/api/v1/batches/{$cultureBatchId}")
            ->assertOk()
            ->assertJsonPath('batch.phase', BatchPhase::CULTURE)
            ->assertJsonPath('batch.current_campus_id', $this->campusId(Campus::CULTURE));

        $this->getJson("/api/v1/batches/{$sphereonBatchId}")
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'batch_not_found');

        $this->getJson("/api/v1/batches/{$logisticsBatchId}")
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'batch_not_found');
    }

    public function test_it_keeps_the_full_visibility_for_the_plant_supervisor(): void
    {
        $cultureBatchId = $this->createBatch();
        $sphereonBatchId = $this->createBatchInPhase(BatchPhase::SPHEREON);
        $logisticsBatchId = $this->createBatchInPhase(BatchPhase::LOGISTICS);

        $this->actingAsUser('Supervisor de Planta');

        $listedIds = array_column($this->getJson('/api/v1/batches')->assertOk()->json('batches'), 'id');

        $this->assertContains($cultureBatchId, $listedIds);
        $this->assertContains($sphereonBatchId, $listedIds);
        $this->assertContains($logisticsBatchId, $listedIds);

        $this->getJson("/api/v1/batches/{$sphereonBatchId}")
            ->assertOk()
            ->assertJsonPath('batch.phase', BatchPhase::SPHEREON)
            ->assertJsonPath('batch.current_campus_id', $this->campusId(Campus::SPHEREON));

        $this->getJson("/api/v1/batches/{$logisticsBatchId}")
            ->assertOk()
            ->assertJsonPath('batch.phase', BatchPhase::LOGISTICS)
            ->assertJsonPath('batch.current_campus_id', $this->campusId(Campus::LOGISTICS));
    }

    /**
     * Resultado microbiológico válido para el registro manual.
     *
     * @return array<string, string>
     */
    private function limsResultPayload(): array
    {
        return [
            'test_type' => LimsResult::TEST_TYPE_MICROBIOLOGY,
            'result' => LimsResult::RESULT_APPROVED,
            'approved_by' => 'Técnico de Control de Calidad',
            'result_date' => '2026-02-10T08:30:00+00:00',
        ];
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
     * Crea un lote en la fase indicada y devuelve su identificador.
     */
    private function createBatchInPhase(string $phase): string
    {
        $batchId = $this->createBatch();
        $referenceId = $this->vaccineReferenceId('Vacuna conjugada intramuscular');

        $this->postJson("/api/v1/batches/{$batchId}/transitions", [
            'to_phase' => BatchPhase::SPHEREON,
        ])->assertOk();

        if ($phase === BatchPhase::SPHEREON) {
            return $batchId;
        }

        // La fase logística exige la aprobación microbiológica de LIMS Labware.
        $this->importLimsApproval($batchId, $referenceId);

        $this->postJson("/api/v1/batches/{$batchId}/transitions", [
            'to_phase' => BatchPhase::LOGISTICS,
        ])->assertOk();

        return $batchId;
    }

    /**
     * Crea un lote ya situado en la fase logística del Campus 3.
     */
    private function createBatchInLogisticsPhase(): string
    {
        return $this->createBatchInPhase(BatchPhase::LOGISTICS);
    }
}
