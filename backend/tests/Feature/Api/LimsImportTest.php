<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Application\Lims\ImportLimsResults;
use App\Domain\Lims\Entity\LimsResult;
use App\Domain\Lims\Service\LimsPayloadValidator;
use App\Domain\VaccineBatch\Entity\Campus;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;
use App\Domain\VaccineBatch\ValueObject\BatchStatus;
use App\Infrastructure\Persistence\Eloquent\Model\LogEntryModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithDatabase;
use Tests\Support\InteractsWithLims;
use Tests\Support\MakesAuthenticatedRequests;
use Tests\TestCase;

/**
 * Importación de los resultados de LIMS Labware (véase TC001, TC002 y TC008).
 */
class LimsImportTest extends TestCase
{
    use InteractsWithDatabase;
    use InteractsWithLims;
    use MakesAuthenticatedRequests;
    use RefreshDatabase;

    public function test_it_imports_the_lims_payloads_without_manual_intervention(): void
    {
        $this->seedCampuses();
        $this->seedVaccineReferences();
        $this->actingAsUser('Supervisor de Planta');

        $batchId = Str::uuid()->toString();
        $referenceId = $this->vaccineReferenceId('Vacuna conjugada intramuscular');

        $client = $this->limsClientWith($this->limsPayload($batchId, $referenceId));

        $this->artisan('lims:sync')->assertExitCode(0);

        $this->assertDatabaseHas('VACCINE_BATCH', [
            'id' => $batchId,
            'reference_id' => $referenceId,
            'phase' => BatchPhase::CULTURE,
            'status' => BatchStatus::IN_PROGRESS,
            'current_campus_id' => $this->campusId(Campus::CULTURE),
        ]);

        $this->assertDatabaseHas('LIMS_RESULT', [
            'batch_id' => $batchId,
            'test_type' => LimsResult::TEST_TYPE_MICROBIOLOGY,
            'result' => LimsResult::RESULT_APPROVED,
            'approved_by' => 'Responsable de LIMS Labware',
        ]);

        $this->assertDatabaseHas('LOG_ENTRY', [
            'batch_id' => $batchId,
            'event_type' => LimsResult::EVENT_TYPE_IMPORTED,
        ]);

        $this->assertContains('read:batch_results', $client->operations());
        $this->assertSame(
            0,
            $client->writeOperations(),
            'La integración con LIMS Labware debe limitarse a operaciones de lectura.',
        );
    }

    public function test_it_rejects_an_inconsistent_payload_without_persisting_or_exposing_the_batch(): void
    {
        $this->seedCampuses();
        $this->seedVaccineReferences();
        $this->actingAsUser('Supervisor de Planta');

        $batchId = Str::uuid()->toString();
        $referenceId = $this->vaccineReferenceId('Vacuna conjugada intramuscular');

        $payload = $this->limsPayload($batchId, $referenceId);
        $payload['results'][0]['result'] = '   ';

        $this->limsClientWith($payload);

        $this->artisan('lims:sync')->assertExitCode(0);

        $this->assertDatabaseMissing('VACCINE_BATCH', ['id' => $batchId]);
        $this->assertDatabaseCount('LIMS_RESULT', 0);

        $this->getJson('/api/v1/batches')
            ->assertOk()
            ->assertJsonCount(0, 'batches');

        $this->getJson("/api/v1/batches/{$batchId}")
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'batch_not_found');

        $entry = $this->logEntry(ImportLimsResults::EVENT_TYPE_REJECTED);

        $this->assertNotNull($entry, 'El intento fallido de importación debe quedar registrado.');
        $this->assertNull($entry->batch_id);
        $this->assertSame(LimsPayloadValidator::ERROR_EMPTY_RESULT, $entry->payload['error_code']);
        $this->assertNotEmpty($entry->payload['error_detail']);
        $this->assertSame($batchId, $entry->payload['batch_reference']);
    }

    public function test_it_exposes_the_origin_and_the_update_date_of_the_imported_data(): void
    {
        $this->seedCampuses();
        $this->seedVaccineReferences();
        $this->actingAsUser('Supervisor de Planta');

        $batchId = Str::uuid()->toString();
        $referenceId = $this->vaccineReferenceId('Vacuna conjugada intramuscular');

        $this->limsClientWith($this->limsPayload($batchId, $referenceId));

        $this->artisan('lims:sync')->assertExitCode(0);

        $this->getJson("/api/v1/batches/{$batchId}")
            ->assertOk()
            ->assertJsonPath('lims_results.0.source', LimsResult::SOURCE_LABWARE)
            ->assertJsonPath('lims_results.0.updated_at', '2026-02-11T09:00:00+00:00');

        $response = $this->getJson("/api/v1/batches/{$batchId}/traceability")
            ->assertOk()
            ->assertJsonPath('batch_id', $batchId);

        $entries = collect($response->json('entries'))
            ->where('event_type', LimsResult::EVENT_TYPE_IMPORTED)
            ->values();

        $this->assertCount(1, $entries, 'El historial debe recoger el resultado importado.');
        $this->assertSame(LimsResult::SOURCE_LABWARE, $entries->first()['payload']['source']);
        $this->assertSame('2026-02-11T09:00:00+00:00', $entries->first()['payload']['updated_at']);
    }

    /**
     * Primera entrada del log estructurado del tipo indicado.
     */
    private function logEntry(string $eventType): ?LogEntryModel
    {
        return LogEntryModel::query()->where('event_type', $eventType)->first();
    }
}
