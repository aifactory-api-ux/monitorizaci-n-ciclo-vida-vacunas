<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\VaccineBatch\Entity\Campus;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;
use App\Domain\VaccineBatch\ValueObject\BatchStatus;
use App\Infrastructure\Persistence\Eloquent\Model\VaccineBatchModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithDatabase;
use Tests\Support\MakesAuthenticatedRequests;
use Tests\TestCase;

/**
 * Alta de lote de vacuna (véase TC015).
 */
class BatchCreationTest extends TestCase
{
    use InteractsWithDatabase;
    use MakesAuthenticatedRequests;
    use RefreshDatabase;

    public function test_it_creates_the_batch_in_culture_phase_at_campus_one(): void
    {
        $this->seedCampuses();
        $this->seedVaccineReferences();
        $this->actingAsUser('Operador de Producción');

        $response = $this->postJson('/api/v1/batches', [
            'reference_id' => $this->vaccineReferenceId('Vacuna conjugada intramuscular'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('phase', BatchPhase::CULTURE)
            ->assertJsonPath('status', BatchStatus::IN_PROGRESS)
            ->assertJsonPath('current_campus_id', $this->campusId(Campus::CULTURE))
            ->assertJsonStructure(['id', 'reference_id', 'phase', 'status', 'current_campus_id', 'created_at', 'updated_at']);

        $this->assertDatabaseHas('VACCINE_BATCH', [
            'id' => $response->json('id'),
            'reference_id' => $this->vaccineReferenceId('Vacuna conjugada intramuscular'),
            'phase' => BatchPhase::CULTURE,
            'status' => BatchStatus::IN_PROGRESS,
            'current_campus_id' => $this->campusId(Campus::CULTURE),
        ]);
    }

    public function test_the_created_batch_has_a_uuid_primary_key(): void
    {
        $this->seedCampuses();
        $this->seedVaccineReferences();
        $this->actingAsUser('Operador de Producción');

        $response = $this->postJson('/api/v1/batches', [
            'reference_id' => $this->vaccineReferenceId('Vacuna conjugada intramuscular'),
        ]);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            (string) $response->json('id'),
        );
    }

    public function test_it_rejects_a_batch_with_an_unknown_vaccine_reference(): void
    {
        $this->seedCampuses();
        $this->actingAsUser('Operador de Producción');

        $response = $this->postJson('/api/v1/batches', [
            'reference_id' => '99999999-9999-4999-8999-999999999999',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'unknown_vaccine_reference');

        $this->assertDatabaseCount('VACCINE_BATCH', 0);
        $this->assertDatabaseCount('PHASE_TRANSITION', 0);
    }

    public function test_it_rejects_a_batch_without_a_vaccine_reference(): void
    {
        $this->seedCampuses();
        $this->actingAsUser('Operador de Producción');

        $this->postJson('/api/v1/batches', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reference_id');

        $this->assertDatabaseCount('VACCINE_BATCH', 0);
    }

    public function test_it_requires_an_authenticated_actor(): void
    {
        $this->seedCampuses();
        $this->seedVaccineReferences();

        $this->postJson('/api/v1/batches', [
            'reference_id' => $this->vaccineReferenceId('Vacuna conjugada intramuscular'),
        ])->assertUnauthorized();

        $this->assertSame(0, VaccineBatchModel::query()->count());
    }
}
