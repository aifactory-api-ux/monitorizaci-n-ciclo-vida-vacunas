<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Application\Operations\MeasureNonFunctionalThresholds;
use App\Domain\Operations\ValueObject\NonFunctionalThresholdReport;
use App\Domain\Operations\ValueObject\ThresholdMeasurement;
use App\Domain\VaccineBatch\Entity\Campus;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;
use App\Domain\VaccineBatch\ValueObject\BatchStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithDatabase;
use Tests\TestCase;

/**
 * Umbrales no funcionales exigidos por el enunciado: carga del panel,
 * percentil 95 de la búsqueda avanzada, latencia de publicación de los
 * eventos y respuesta de las rutas protegidas sin token (véase NFR001,
 * NFR003, NFR006 y NFR008).
 */
class NonFunctionalThresholdsTest extends TestCase
{
    use InteractsWithDatabase;
    use RefreshDatabase;

    /**
     * Tamaño del conjunto de datos de la medición.
     */
    private const DATASET_SIZE = 300;

    /**
     * Umbral de carga del panel, en segundos.
     */
    private const DASHBOARD_LIMIT_SECONDS = 3.0;

    /**
     * Umbral del percentil 95 de la búsqueda, en segundos.
     */
    private const SEARCH_P95_LIMIT_SECONDS = 2.0;

    /**
     * Umbral de latencia de los eventos, en segundos.
     */
    private const EVENT_LATENCY_LIMIT_SECONDS = 1.0;

    /**
     * Código de estado exigido sin token Bearer.
     */
    private const PROTECTED_STATUS_CODE = 401;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCampuses();
        $this->seedVaccineReferences();
        $this->seedDataset();
    }

    public function test_it_measures_the_dashboard_load_below_three_seconds(): void
    {
        $measurement = $this->measurementFor('NFR001');

        $this->assertNotNull($measurement, 'La carga del panel debe estar medida.');
        $this->assertTrue(
            $measurement->isSatisfied(),
            sprintf('La carga del panel ha sido de %.3f segundos.', $measurement->measured()),
        );
        $this->assertLessThan(self::DASHBOARD_LIMIT_SECONDS, $measurement->measured());
    }

    public function test_it_measures_the_p95_of_the_advanced_search_below_two_seconds(): void
    {
        $measurement = $this->measurementFor('NFR008');

        $this->assertNotNull($measurement, 'El percentil 95 de la búsqueda debe estar medido.');
        $this->assertTrue(
            $measurement->isSatisfied(),
            sprintf('El percentil 95 de la búsqueda ha sido de %.3f segundos.', $measurement->measured()),
        );
        $this->assertLessThan(self::SEARCH_P95_LIMIT_SECONDS, $measurement->measured());
    }

    public function test_it_measures_the_latency_of_the_events_below_one_second(): void
    {
        $measurement = $this->measurementFor('NFR006');

        $this->assertNotNull($measurement, 'La latencia de los eventos debe estar medida.');
        $this->assertTrue(
            $measurement->isSatisfied(),
            sprintf('La latencia de los eventos ha sido de %.3f segundos.', $measurement->measured()),
        );
        $this->assertLessThan(self::EVENT_LATENCY_LIMIT_SECONDS, $measurement->measured());
    }

    public function test_it_measures_the_unauthorized_response_of_the_protected_routes_without_a_token(): void
    {
        $measurement = $this->measurementFor('NFR003');

        $this->assertNotNull($measurement, 'La respuesta de las rutas protegidas debe estar medida.');
        $this->assertSame((float) self::PROTECTED_STATUS_CODE, $measurement->measured());
        $this->assertTrue($measurement->isSatisfied());
    }

    public function test_the_report_complies_with_every_measured_threshold(): void
    {
        $report = $this->report();

        $this->assertTrue(
            $report->isCompliant(),
            implode(' | ', array_map(
                fn (ThresholdMeasurement $measurement): string => sprintf(
                    '%s ha medido %.3f %s con un límite de %.3f %s',
                    $measurement->criterion(),
                    $measurement->measured(),
                    $measurement->unit(),
                    $measurement->limit(),
                    $measurement->unit(),
                ),
                $report->violations(),
            )),
        );
        $this->assertCount(4, $report->measurements());
    }

    /**
     * Informe con las mediciones de los umbrales.
     */
    private function report(): NonFunctionalThresholdReport
    {
        return $this->app->make(MeasureNonFunctionalThresholds::class)->execute();
    }

    /**
     * Medición del criterio indicado.
     */
    private function measurementFor(string $criterion): ?ThresholdMeasurement
    {
        return $this->report()->measurementFor($criterion);
    }

    /**
     * Siembra el conjunto de datos con el que se mide la carga del panel y
     * el percentil 95 de la búsqueda.
     *
     * Los lotes se insertan en bloque para que la siembra no domine el
     * tiempo de la medición.
     */
    private function seedDataset(): void
    {
        $references = [
            $this->vaccineReferenceId('Vacuna conjugada intramuscular'),
            $this->vaccineReferenceId('Vacuna combinada trivalente'),
            $this->vaccineReferenceId('Vacuna oral bivalente'),
        ];

        $campuses = [
            BatchPhase::CULTURE => $this->cultureCampusId(),
            BatchPhase::SPHEREON => $this->campusId(Campus::SPHEREON),
            BatchPhase::LOGISTICS => $this->campusId(Campus::LOGISTICS),
        ];

        $phases = array_keys($campuses);
        $rows = [];

        for ($index = 0; $index < self::DATASET_SIZE; $index++) {
            $phase = $phases[$index % count($phases)];

            $rows[] = [
                'id' => (string) Str::uuid(),
                'reference_id' => $references[$index % count($references)],
                'phase' => $phase,
                'status' => BatchStatus::IN_PROGRESS,
                'current_campus_id' => $campuses[$phase],
                'created_at' => '2026-03-01 08:00:00',
                'updated_at' => '2026-03-01 08:00:00',
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('VACCINE_BATCH')->insert($chunk);
        }
    }
}
