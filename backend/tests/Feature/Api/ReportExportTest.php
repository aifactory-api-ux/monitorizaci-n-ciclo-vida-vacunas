<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Alert\ValueObject\AlertType;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ExtractsPdfText;
use Tests\Support\InteractsWithDatabase;
use Tests\Support\InteractsWithLims;
use Tests\Support\MakesAuthenticatedRequests;
use Tests\TestCase;

/**
 * Reporte de trazabilidad del lote y sus exportaciones para auditoría
 * regulatoria (véase TC011, TC003 y D15).
 */
class ReportExportTest extends TestCase
{
    use ExtractsPdfText;
    use InteractsWithDatabase;
    use InteractsWithLims;
    use MakesAuthenticatedRequests;
    use RefreshDatabase;

    /**
     * Ruta del fichero de lecturas que publica la monitorización.
     */
    private string $feedPath;

    protected function setUp(): void
    {
        parent::setUp();

        $feedPath = tempnam(sys_get_temp_dir(), 'lecturas-monitorizacion-');

        $this->assertIsString($feedPath);

        $this->feedPath = $feedPath;

        $this->publishReadings([]);

        config(['monitoring.feed.path' => $this->feedPath]);

        $this->seedCampuses();
        $this->seedVaccineReferences();
    }

    protected function tearDown(): void
    {
        if (is_file($this->feedPath)) {
            unlink($this->feedPath);
        }

        parent::tearDown();
    }

    public function test_it_exposes_the_report_with_the_transitions_the_lims_results_and_the_alerts(): void
    {
        [$batchId, $referenceId] = $this->completeBatch();

        $response = $this->getJson("/api/v1/reports/traceability/{$batchId}")
            ->assertOk()
            ->assertJsonPath('batch_id', $batchId)
            ->assertJsonPath('reference_id', $referenceId)
            ->assertJsonPath('phase', BatchPhase::LOGISTICS);

        $this->assertCount(2, $response->json('transitions'));
        $this->assertCount(1, $response->json('lims_results'));
        $this->assertCount(1, $response->json('alerts'));
        $this->assertCount(3, $response->json('campus_sequence'));
        $this->assertNotEmpty($response->json('entries'));
    }

    public function test_it_exports_the_traceability_report_in_csv(): void
    {
        [$batchId] = $this->completeBatch();

        $response = $this->get("/api/v1/reports/traceability/{$batchId}.csv");

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        $content = (string) $response->getContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content, 'El CSV debe llevar la marca de orden de bytes UTF-8.');
        $this->assertStringContainsString('seccion,campo,valor', $content);
        $this->assertStringContainsString('Cabecera', $content);
        $this->assertStringContainsString('Transicion', $content);
        $this->assertStringContainsString('Resultado LIMS', $content);
        $this->assertStringContainsString('Alerta', $content);
        $this->assertStringContainsString('Auditoria', $content);
        $this->assertStringContainsString($batchId, $content);
        $this->assertStringContainsString(BatchPhase::CULTURE, $content);
        $this->assertStringContainsString(BatchPhase::SPHEREON, $content);
        $this->assertStringContainsString(BatchPhase::LOGISTICS, $content);
        $this->assertStringContainsString(AlertType::THERMAL_DEVIATION, $content);
    }

    public function test_it_exports_the_traceability_report_in_pdf(): void
    {
        [$batchId, $referenceId] = $this->completeBatch();

        $response = $this->get("/api/v1/reports/traceability/{$batchId}.pdf");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));

        $content = (string) $response->getContent();

        $this->assertStringStartsWith('%PDF', $content, 'El fichero exportado debe ser un PDF válido.');

        $text = $this->pdfText($content);

        $this->assertStringContainsString($referenceId, $text, 'El PDF debe identificar la referencia del lote.');
        $this->assertStringContainsString(BatchPhase::CULTURE, $text);
        $this->assertStringContainsString(BatchPhase::SPHEREON, $text);
        $this->assertStringContainsString(BatchPhase::LOGISTICS, $text);
    }

    public function test_it_returns_not_found_for_the_report_of_an_unknown_batch(): void
    {
        $this->actingAsUser('Supervisor de Planta');

        $this->getJson('/api/v1/reports/traceability/99999999-9999-4999-8999-999999999999')
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'batch_not_found');
    }

    /**
     * Crea un lote que recorre los tres campus, importa la aprobación
     * microbiológica y genera una alerta operativa.
     *
     * @return array{0: string, 1: string}
     */
    private function completeBatch(): array
    {
        $this->actingAsUser('Operador de Producción');

        $response = $this->postJson('/api/v1/batches', [
            'reference_id' => $this->vaccineReferenceId('Vacuna conjugada intramuscular'),
        ]);

        $response->assertCreated();

        $batchId = (string) $response->json('id');
        $referenceId = (string) $response->json('reference_id');

        $this->postJson("/api/v1/batches/{$batchId}/transitions", [
            'to_phase' => BatchPhase::SPHEREON,
        ])->assertOk();

        $this->importLimsApproval($batchId, $referenceId);

        $this->postJson("/api/v1/batches/{$batchId}/transitions", [
            'to_phase' => BatchPhase::LOGISTICS,
        ])->assertOk();

        $this->publishReadings([
            [
                'batch_id' => $batchId,
                'thermal_deviation' => 4.2,
                'detected_at' => '2026-01-05T09:30:00+00:00',
            ],
        ]);

        $this->artisan('alerts:monitor')->assertSuccessful();

        return [$batchId, $referenceId];
    }

    /**
     * Publica las lecturas de la monitorización.
     *
     * @param  list<array<string, mixed>>  $readings
     */
    private function publishReadings(array $readings): void
    {
        file_put_contents($this->feedPath, json_encode($readings, JSON_THROW_ON_ERROR));
    }
}
