<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Audit\Entity\LogEntry;
use App\Domain\Audit\Repository\AuditTrailRepository;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\VaccineBatch\Entity\Campus;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;
use App\Domain\VaccineBatch\ValueObject\BatchStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithDatabase;
use Tests\Support\InteractsWithLims;
use Tests\Support\MakesAuthenticatedRequests;
use Tests\TestCase;

/**
 * Trazabilidad completa del lote e historial inmutable de auditoría
 * (véase TC003, TC020 y TC022).
 */
class TraceabilityTest extends TestCase
{
    use InteractsWithDatabase;
    use InteractsWithLims;
    use MakesAuthenticatedRequests;
    use RefreshDatabase;

    public function test_it_exposes_the_complete_traceability_of_the_batch_between_campuses(): void
    {
        [$batchId, $referenceId] = $this->createBatch();

        $this->postJson("/api/v1/batches/{$batchId}/transitions", [
            'to_phase' => BatchPhase::SPHEREON,
        ])->assertOk();

        $this->importLimsApproval($batchId, $referenceId);

        $this->postJson("/api/v1/batches/{$batchId}/transitions", [
            'to_phase' => BatchPhase::LOGISTICS,
        ])->assertOk();

        $response = $this->getJson("/api/v1/batches/{$batchId}/traceability")
            ->assertOk()
            ->assertJsonPath('batch_id', $batchId)
            ->assertJsonPath('phase', BatchPhase::LOGISTICS)
            ->assertJsonPath('status', BatchStatus::IN_PROGRESS)
            ->assertJsonPath('current_campus_id', $this->campusId(Campus::LOGISTICS));

        $sequence = $response->json('campus_sequence');

        $this->assertCount(3, $sequence, 'El lote recorre los tres campus.');
        $this->assertSame(BatchPhase::CULTURE, $sequence[0]['phase']);
        $this->assertSame(Campus::CULTURE, $sequence[0]['campus_name']);
        $this->assertSame(BatchPhase::SPHEREON, $sequence[1]['phase']);
        $this->assertSame(Campus::SPHEREON, $sequence[1]['campus_name']);
        $this->assertSame(BatchPhase::LOGISTICS, $sequence[2]['phase']);
        $this->assertSame(Campus::LOGISTICS, $sequence[2]['campus_name']);

        $transitions = $response->json('transitions');

        $this->assertCount(2, $transitions);
        $this->assertSame(BatchPhase::CULTURE, $transitions[0]['from_phase']);
        $this->assertSame(BatchPhase::SPHEREON, $transitions[0]['to_phase']);
        $this->assertSame(BatchPhase::SPHEREON, $transitions[1]['from_phase']);
        $this->assertSame(BatchPhase::LOGISTICS, $transitions[1]['to_phase']);
        $this->assertLessThanOrEqual(
            strtotime((string) $transitions[1]['transition_date']),
            strtotime((string) $transitions[0]['transition_date']),
            'Las transiciones deben venir ordenadas cronológicamente.',
        );

        $this->assertNotEmpty($response->json('entries'), 'La trazabilidad incluye el historial auditable.');
        $this->assertCount(1, $response->json('lims_results'));
    }

    public function test_it_returns_an_empty_transition_list_for_a_new_batch(): void
    {
        [$batchId] = $this->createBatch();

        $response = $this->getJson("/api/v1/batches/{$batchId}/traceability")
            ->assertOk()
            ->assertJsonPath('batch_id', $batchId)
            ->assertJsonPath('phase', BatchPhase::CULTURE)
            ->assertJsonPath('status', BatchStatus::IN_PROGRESS)
            ->assertJsonPath('current_campus_id', $this->campusId(Campus::CULTURE));

        $this->assertSame([], $response->json('transitions'));
        $this->assertSame([], $response->json('alerts'));
        $this->assertCount(1, $response->json('campus_sequence'));
        $this->assertSame(Campus::CULTURE, $response->json('campus_sequence.0.campus_name'));
    }

    public function test_it_returns_not_found_for_an_unknown_batch(): void
    {
        $this->seedCampuses();
        $this->actingAsUser('Operador de Producción');

        $this->getJson('/api/v1/batches/99999999-9999-4999-8999-999999999999/traceability')
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'batch_not_found');
    }

    public function test_the_auditor_accesses_the_whole_audit_trail(): void
    {
        [$batchId] = $this->createBatch();

        $this->actingAsUser('Auditor / Administrador IT');

        $response = $this->getJson('/api/v1/audit/logs')
            ->assertOk()
            // El alta del lote y la propia petición de escritura quedan
            // registradas en el log inmutable.
            ->assertJsonPath('total', 2)
            ->assertJsonPath('pagination.page', 1);

        $entries = $response->json('entries');

        $this->assertCount(2, $entries);
        $this->assertTrue(Uuid::isValid((string) $entries[0]['id']));
        $this->assertSame($batchId, $entries[0]['batch_id']);
        $this->assertNotEmpty($entries[0]['event_type']);
        $this->assertNotEmpty($entries[0]['payload']);
        $this->assertNotEmpty($entries[0]['created_at']);

        $eventTypes = array_column($entries, 'event_type');

        $this->assertContains('batch.created', $eventTypes);
        $this->assertContains('api.write.requested', $eventTypes);
    }

    public function test_it_consults_an_event_of_the_audit_trail_by_its_uuid(): void
    {
        $this->createBatch();

        $this->actingAsUser('Auditor / Administrador IT');

        $entryId = $this->firstEntryId();

        $response = $this->getJson("/api/v1/audit/logs/{$entryId}")
            ->assertOk()
            ->assertJsonPath('id', $entryId);

        $this->assertNotEmpty($response->json('event_type'));
        $this->assertIsArray($response->json('payload'));
        $this->assertNotEmpty($response->json('user_id'));
        $this->assertNotEmpty($response->json('created_at'));
    }

    public function test_it_denies_the_audit_trail_to_a_role_that_is_not_the_auditor(): void
    {
        $this->actingAsUser('Supervisor de Planta');

        $this->getJson('/api/v1/audit/logs')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'audit_trail_forbidden');

        $this->getJson('/api/v1/audit/logs/99999999-9999-4999-8999-999999999999')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'audit_trail_forbidden');
    }

    public function test_it_returns_not_found_for_an_unknown_event_uuid(): void
    {
        $this->actingAsUser('Auditor / Administrador IT');

        $this->getJson('/api/v1/audit/logs/99999999-9999-4999-8999-999999999999')
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'audit_entry_not_found');
    }

    /**
     * Identificador de la primera entrada del historial de auditoría.
     */
    private function firstEntryId(): string
    {
        $page = $this->app->make(AuditTrailRepository::class)->findAll(1, 0);

        $this->assertNotEmpty($page);
        $this->assertInstanceOf(LogEntry::class, $page[0]);

        return $page[0]->id();
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
