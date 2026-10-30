<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Application\Batches\AdvanceBatchPhase;
use App\Domain\Alert\Entity\Alert;
use App\Domain\Alert\Repository\AlertRepository;
use App\Domain\Alert\ValueObject\AlertSeverity;
use App\Domain\Alert\ValueObject\AlertStatus;
use App\Domain\Alert\ValueObject\AlertType;
use App\Domain\Search\Port\BatchSearchRepository;
use App\Domain\VaccineBatch\Entity\Campus;
use App\Domain\VaccineBatch\Entity\VaccineBatch;
use App\Domain\VaccineBatch\Repository\VaccineBatchRepository;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;
use App\Domain\VaccineBatch\ValueObject\BatchStatus;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Doubles\CountingBatchSearchRepository;
use Tests\Support\InteractsWithDatabase;
use Tests\Support\MakesAuthenticatedRequests;
use Tests\TestCase;

/**
 * Búsqueda avanzada de lotes: filtros, paginación y caché de resultados
 * (véase D14 y TC020).
 */
class BatchSearchTest extends TestCase
{
    use InteractsWithDatabase;
    use MakesAuthenticatedRequests;
    use RefreshDatabase;

    /**
     * Tamaño del conjunto de datos de la medición de rendimiento.
     */
    private const PERFORMANCE_DATASET_SIZE = 1200;

    /**
     * Número de mediciones que componen cada percentil.
     */
    private const PERFORMANCE_SAMPLES = 20;

    /**
     * Percentil 95 máximo admitido, en segundos.
     */
    private const PERFORMANCE_P95_LIMIT_SECONDS = 2.0;

    /**
     * Transacciones por hora mínimas que debe admitir el despliegue.
     */
    private const PERFORMANCE_MIN_TRANSACTIONS_PER_HOUR = 2000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCampuses();
        $this->seedVaccineReferences();
    }

    public function test_it_filters_the_batches_by_product_family(): void
    {
        $this->actingAsUser('Operador de Producción');

        $conjugada = $this->createBatch('Vacuna conjugada intramuscular', '2026-01-05 08:00:00');
        $this->createBatch('Vacuna combinada trivalente', '2026-01-06 08:00:00');

        $this->search(['family' => 'Vacunas conjugadas'])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonCount(1, 'batches')
            ->assertJsonPath('batches.0.id', $conjugada)
            ->assertJsonPath('batches.0.reference_family', 'Vacunas conjugadas')
            ->assertJsonPath('batches.0.reference_name', 'Vacuna conjugada intramuscular');
    }

    public function test_it_filters_the_batches_by_phase(): void
    {
        $this->actingAsUser('Operador de Producción');

        $inCulture = $this->createBatch('Vacuna conjugada intramuscular', '2026-01-05 08:00:00');
        $inSphereon = $this->createBatch('Vacuna combinada trivalente', '2026-01-06 08:00:00');

        $this->advancePhase($inSphereon, BatchPhase::SPHEREON, '2026-01-07 08:00:00');

        $this->search(['phase' => BatchPhase::SPHEREON])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('batches.0.id', $inSphereon)
            ->assertJsonPath('batches.0.phase', BatchPhase::SPHEREON);

        $this->search(['phase' => BatchPhase::CULTURE])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('batches.0.id', $inCulture);
    }

    public function test_it_filters_the_batches_by_alert_status(): void
    {
        $this->actingAsUser('Operador de Producción');

        $withCriticalAlert = $this->createBatch('Vacuna conjugada intramuscular', '2026-01-05 08:00:00');
        $withoutAlert = $this->createBatch('Vacuna combinada trivalente', '2026-01-06 08:00:00');

        $this->raiseAlert($withCriticalAlert, AlertSeverity::CRITICAL, '2026-01-07 08:00:00');

        $this->search(['alert_status' => AlertStatus::WITH_CRITICAL_ALERT])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('batches.0.id', $withCriticalAlert);

        $this->search(['alert_status' => AlertStatus::WITHOUT_CRITICAL_ALERT])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('batches.0.id', $withoutAlert);
    }

    public function test_it_filters_the_batches_by_date_range(): void
    {
        $this->actingAsUser('Operador de Producción');

        $january = $this->createBatch('Vacuna conjugada intramuscular', '2026-01-10 08:00:00');
        $this->createBatch('Vacuna combinada trivalente', '2026-02-10 08:00:00');

        $this->search(['date_from' => '2026-01-01', 'date_to' => '2026-01-31'])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('batches.0.id', $january);

        $this->search(['date_from' => '2026-01-15', 'date_to' => '2026-01-31'])
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonCount(0, 'batches');
    }

    public function test_it_returns_the_intersection_of_the_three_filters(): void
    {
        $this->actingAsUser('Operador de Producción');

        $target = $this->createBatch('Vacuna conjugada intramuscular', '2026-01-05 08:00:00');
        $this->advancePhase($target, BatchPhase::SPHEREON, '2026-01-06 08:00:00');
        $this->raiseAlert($target, AlertSeverity::CRITICAL, '2026-01-07 08:00:00');

        $otherFamily = $this->createBatch('Vacuna combinada trivalente', '2026-01-05 09:00:00');
        $this->advancePhase($otherFamily, BatchPhase::SPHEREON, '2026-01-06 09:00:00');
        $this->raiseAlert($otherFamily, AlertSeverity::CRITICAL, '2026-01-07 09:00:00');

        $withoutCriticalAlert = $this->createBatch('Vacuna conjugada intramuscular', '2026-01-05 10:00:00');
        $this->advancePhase($withoutCriticalAlert, BatchPhase::SPHEREON, '2026-01-06 10:00:00');
        $this->raiseAlert($withoutCriticalAlert, AlertSeverity::WARNING, '2026-01-07 10:00:00');

        $this->search([
            'family' => 'Vacunas conjugadas',
            'phase' => BatchPhase::SPHEREON,
            'alert_status' => AlertStatus::WITH_CRITICAL_ALERT,
        ])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonCount(1, 'batches')
            ->assertJsonPath('batches.0.id', $target);
    }

    public function test_it_returns_an_empty_set_when_no_batch_satisfies_the_filters(): void
    {
        $this->actingAsUser('Operador de Producción');

        $this->createBatch('Vacuna conjugada intramuscular', '2026-01-05 08:00:00');

        $this->search([
            'family' => 'Vacunas conjugadas',
            'phase' => BatchPhase::LOGISTICS,
            'alert_status' => AlertStatus::WITH_CRITICAL_ALERT,
        ])
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonCount(0, 'batches')
            ->assertJsonPath('page', 1);
    }

    public function test_it_paginates_the_results_and_returns_the_total(): void
    {
        $this->actingAsUser('Operador de Producción');

        $older = $this->createBatch('Vacuna conjugada intramuscular', '2026-01-05 08:00:00');
        $middle = $this->createBatch('Vacuna conjugada intramuscular', '2026-01-06 08:00:00');
        $newest = $this->createBatch('Vacuna conjugada intramuscular', '2026-01-07 08:00:00');

        $this->search(['per_page' => 2])
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('page', 1)
            ->assertJsonPath('per_page', 2)
            ->assertJsonPath('last_page', 2)
            ->assertJsonCount(2, 'batches')
            ->assertJsonPath('batches.0.id', $newest)
            ->assertJsonPath('batches.1.id', $middle);

        $this->search(['per_page' => 2, 'page' => 2])
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonCount(1, 'batches')
            ->assertJsonPath('batches.0.id', $older);
    }

    public function test_it_keeps_a_stable_order_for_batches_created_at_the_same_instant(): void
    {
        $this->actingAsUser('Operador de Producción');

        $first = $this->createBatch('Vacuna conjugada intramuscular', '2026-01-05 08:00:00');
        $second = $this->createBatch('Vacuna conjugada intramuscular', '2026-01-05 08:00:00');

        $expected = [$first, $second];
        sort($expected);

        $response = $this->search()->assertOk()->assertJsonPath('total', 2)->assertJsonCount(2, 'batches');

        $this->assertSame($expected, array_column($response->json('batches'), 'id'));
    }

    public function test_it_reuses_the_cached_result_for_the_same_filters(): void
    {
        $this->actingAsUser('Operador de Producción');

        $this->createBatch('Vacuna conjugada intramuscular', '2026-01-05 08:00:00');

        $repository = new CountingBatchSearchRepository($this->app->make(BatchSearchRepository::class));
        $this->app->instance(BatchSearchRepository::class, $repository);

        $this->search(['family' => 'Vacunas conjugadas'])->assertOk()->assertJsonPath('total', 1);
        $this->search(['family' => 'Vacunas conjugadas'])->assertOk()->assertJsonPath('total', 1);

        $this->assertSame(1, $repository->calls(), 'La segunda consulta debe servirse desde la caché.');
    }

    public function test_it_invalidates_the_cached_result_when_the_batch_phase_changes(): void
    {
        $this->actingAsUser('Operador de Producción');

        $batchId = $this->createBatch('Vacuna conjugada intramuscular', '2026-01-05 08:00:00');

        $this->search(['phase' => BatchPhase::CULTURE])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('batches.0.id', $batchId);

        $this->advancePhase($batchId, BatchPhase::SPHEREON, '2026-01-06 08:00:00');

        $this->search(['phase' => BatchPhase::CULTURE])
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonCount(0, 'batches');

        $this->search(['phase' => BatchPhase::SPHEREON])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('batches.0.id', $batchId)
            ->assertJsonPath('batches.0.phase', BatchPhase::SPHEREON);
    }

    public function test_it_rejects_an_unknown_filter_literal(): void
    {
        $this->actingAsUser('Operador de Producción');

        $this->search(['phase' => 'Fase inexistente'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('phase');
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/v1/search/batches')->assertStatus(401);
    }

    public function test_it_keeps_the_p95_of_the_advanced_search_below_two_seconds_over_a_representative_dataset(): void
    {
        $this->actingAsUser('Operador de Producción');

        $seeded = $this->seedPerformanceDataset();

        $this->assertGreaterThanOrEqual(
            1000,
            $seeded,
            'La medición del percentil 95 exige un conjunto de datos de al menos mil lotes.',
        );

        $durations = [];

        for ($sample = 1; $sample <= self::PERFORMANCE_SAMPLES; $sample++) {
            $startedAt = microtime(true);

            // Cada medición cambia la página solicitada, de modo que la
            // caché de resultados no sirve ninguna de ellas.
            $this->search([
                'family' => 'Vacunas conjugadas',
                'phase' => BatchPhase::CULTURE,
                'page' => $sample,
                'per_page' => 25,
            ])->assertOk();

            $durations[] = microtime(true) - $startedAt;
        }

        $p95 = $this->percentile95($durations);

        $this->assertLessThan(
            self::PERFORMANCE_P95_LIMIT_SECONDS,
            $p95,
            sprintf(
                'El percentil 95 de la búsqueda avanzada ha sido de %.3f segundos sobre %d lotes.',
                $p95,
                $seeded,
            ),
        );
    }

    public function test_it_keeps_the_p95_of_the_batch_listing_below_two_seconds_over_a_representative_dataset(): void
    {
        $this->actingAsUser('Supervisor de Planta');

        $seeded = $this->seedPerformanceDataset();

        $this->assertGreaterThanOrEqual(
            1000,
            $seeded,
            'La medición del percentil 95 exige un conjunto de datos de al menos mil lotes.',
        );

        $durations = [];

        for ($sample = 1; $sample <= self::PERFORMANCE_SAMPLES; $sample++) {
            $startedAt = microtime(true);

            $this->getJson('/api/v1/batches')->assertOk();

            $durations[] = microtime(true) - $startedAt;
        }

        $p95 = $this->percentile95($durations);

        $this->assertLessThan(
            self::PERFORMANCE_P95_LIMIT_SECONDS,
            $p95,
            sprintf(
                'El percentil 95 del listado de lotes ha sido de %.3f segundos sobre %d lotes.',
                $p95,
                $seeded,
            ),
        );
    }

    public function test_it_handles_thousands_of_transactions_per_hour_without_errors_or_duplicates(): void
    {
        $this->actingAsUser('Operador de Producción');

        $referenceId = $this->vaccineReferenceId('Vacuna conjugada intramuscular');

        $durations = [];
        $created = [];
        $startedAt = microtime(true);

        for ($transaction = 1; $transaction <= 40; $transaction++) {
            $writeStartedAt = microtime(true);

            $response = $this->postJson('/api/v1/batches', [
                'reference_id' => $referenceId,
            ]);

            $response->assertCreated();

            $durations[] = microtime(true) - $writeStartedAt;
            $created[] = (string) $response->json('id');

            $readStartedAt = microtime(true);

            $this->search(['phase' => BatchPhase::CULTURE, 'page' => $transaction])->assertOk();

            $durations[] = microtime(true) - $readStartedAt;
        }

        $elapsed = microtime(true) - $startedAt;
        $transactions = count($durations);
        $perHour = (int) round($transactions / max($elapsed, 0.000001) * 3600);

        $this->assertSame(
            count($created),
            count(array_unique($created)),
            'Las operaciones de escritura no pueden generar lotes duplicados.',
        );

        $this->assertSame(
            count($created),
            DB::table('VACCINE_BATCH')->count(),
            'Cada operación de escritura debe quedar persistida exactamente una vez.',
        );

        $this->assertGreaterThanOrEqual(
            self::PERFORMANCE_MIN_TRANSACTIONS_PER_HOUR,
            $perHour,
            sprintf('El despliegue ha admitido %d transacciones por hora.', $perHour),
        );

        $p95 = $this->percentile95($durations);

        $this->assertLessThan(
            self::PERFORMANCE_P95_LIMIT_SECONDS,
            $p95,
            sprintf('El percentil 95 de las operaciones mixtas ha sido de %.3f segundos.', $p95),
        );
    }

    /**
     * Percentil 95 de las duraciones medidas, en segundos.
     *
     * @param  list<float>  $durations
     */
    private function percentile95(array $durations): float
    {
        sort($durations);

        $index = (int) ceil(0.95 * count($durations)) - 1;

        return $durations[max($index, 0)];
    }

    /**
     * siembra un conjunto de datos representativo para la medición de
     * rendimiento.
     *
     * Los lotes se insertan en bloque para que la propia siembra no domine
     * el tiempo de la medición: el conjunto reparte los lotes entre las
     * referencias del catálogo, entre las fases del ciclo de vida y entre
     * varias fechas de alta.
     *
     * @return int Número de lotes sembrados.
     */
    private function seedPerformanceDataset(int $size = self::PERFORMANCE_DATASET_SIZE): int
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
        $baseDate = new DateTimeImmutable('2026-03-01 08:00:00');

        $rows = [];

        for ($index = 0; $index < $size; $index++) {
            $phase = $phases[$index % count($phases)];
            $createdAt = $baseDate->modify(sprintf('-%d minutes', $index));

            $rows[] = [
                'id' => (string) Str::uuid(),
                'reference_id' => $references[$index % count($references)],
                'phase' => $phase,
                'status' => BatchStatus::IN_PROGRESS,
                'current_campus_id' => $campuses[$phase],
                'created_at' => $createdAt->format('Y-m-d H:i:s'),
                'updated_at' => $createdAt->format('Y-m-d H:i:s'),
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('VACCINE_BATCH')->insert($chunk);
        }

        return DB::table('VACCINE_BATCH')->count();
    }

    /**
     * Consulta el endpoint de búsqueda con los filtros indicados.
     *
     * @param  array<string, string|int>  $filters
     */
    private function search(array $filters = []): TestResponse
    {
        $query = http_build_query($filters);

        return $this->getJson('/api/v1/search/batches'.($query === '' ? '' : '?'.$query));
    }

    /**
     * Crea un lote con la fecha de alta indicada y devuelve su identificador.
     */
    private function createBatch(string $referenceName, string $createdAt): string
    {
        $batch = VaccineBatch::create(
            $this->vaccineReferenceId($referenceName),
            $this->cultureCampusId(),
            new DateTimeImmutable($createdAt),
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

    /**
     * Registra una alerta sobre el lote con la severidad indicada.
     */
    private function raiseAlert(string $batchId, string $severity, string $at): void
    {
        $alert = Alert::raise(
            $batchId,
            AlertType::from(AlertType::THERMAL_DEVIATION),
            AlertSeverity::from($severity),
            'Desviación térmica detectada en la cámara de cultivo.',
            new DateTimeImmutable($at),
        );

        $this->app->make(AlertRepository::class)->save($alert);
    }
}
