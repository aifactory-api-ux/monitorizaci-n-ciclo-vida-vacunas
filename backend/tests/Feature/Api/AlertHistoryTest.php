<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Alert\Entity\Alert;
use App\Domain\Alert\ValueObject\AlertSeverity;
use App\Domain\Alert\ValueObject\AlertType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Doubles\FakeAlertNotifier;
use Tests\Support\InteractsWithDatabase;
use Tests\Support\MakesAuthenticatedRequests;
use Tests\TestCase;

/**
 * Alertas operativas de prioridad máxima: generación automática sin
 * intervención manual, notificación a los canales configurados y consulta del
 * histórico (véase TC005, TC010, TC019 y TC023).
 */
class AlertHistoryTest extends TestCase
{
    use InteractsWithDatabase;
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

    public function test_it_generates_the_critical_alert_automatically_without_any_user_request(): void
    {
        $batchId = $this->createBatch();

        $this->publishReadings([
            [
                'batch_id' => $batchId,
                'thermal_deviation' => 4.2,
                'detected_at' => '2026-01-05T09:30:00+00:00',
            ],
        ]);

        $this->artisan('alerts:monitor')
            ->expectsOutputToContain('Alertas generadas: 1.')
            ->assertSuccessful();

        $this->assertDatabaseHas('ALERT', [
            'batch_id' => $batchId,
            'alert_type' => AlertType::THERMAL_DEVIATION,
            'severity' => AlertSeverity::CRITICAL,
        ]);

        $this->assertDatabaseHas('LOG_ENTRY', [
            'batch_id' => $batchId,
            'event_type' => Alert::EVENT_TYPE_RAISED,
        ]);
    }

    public function test_it_exposes_the_generated_alert_with_its_type_batch_severity_message_and_date(): void
    {
        $batchId = $this->createBatch();

        $this->publishReadings([
            [
                'batch_id' => $batchId,
                'thermal_deviation' => 4.2,
                'detected_at' => '2026-01-05T09:30:00+00:00',
            ],
        ]);

        $this->artisan('alerts:monitor')->assertSuccessful();

        $this->actingAsUser('Supervisor de Planta');

        $threshold = (float) config('monitoring.thermal.deviation_threshold');

        $response = $this->getJson('/api/v1/alerts/history')->assertOk();

        $response->assertJsonCount(1, 'alerts');
        $response->assertJsonPath('alerts.0.batch_id', $batchId);
        $response->assertJsonPath('alerts.0.alert_type', AlertType::THERMAL_DEVIATION);
        $response->assertJsonPath('alerts.0.severity', AlertSeverity::CRITICAL);
        $response->assertJsonPath('alerts.0.status', AlertSeverity::CRITICAL);
        $response->assertJsonPath('alerts.0.message', sprintf(
            'Desviación térmica de %.2f °C sobre el umbral permitido de %.2f °C.',
            4.2,
            $threshold,
        ));
        $response->assertJsonPath('alerts.0.created_at', '2026-01-05T09:30:00+00:00');
    }

    public function test_it_notifies_the_configured_channels_when_a_critical_alert_is_generated(): void
    {
        $email = $this->useFakeChannel('email');
        $sms = $this->useFakeChannel('sms');

        $this->enableChannel('email');
        $this->enableChannel('sms');

        $batchId = $this->createBatch();

        $this->publishReadings([
            [
                'batch_id' => $batchId,
                'sphereon_anomaly' => 7.5,
                'detected_at' => '2026-01-05T11:45:00+00:00',
            ],
        ]);

        $this->artisan('alerts:monitor')->assertSuccessful();

        $this->assertSame(1, $email->count());
        $this->assertSame(1, $sms->count());

        $notification = $email->notifications()[0];

        $this->assertSame($batchId, $notification->batchId());
        $this->assertSame(AlertType::SPHEREON_ANOMALY, $notification->alertType()->value());
        $this->assertSame(AlertSeverity::CRITICAL, $notification->severity()->value());
        $this->assertTrue($notification->isCritical());
    }

    public function test_it_only_notifies_the_channels_enabled_in_the_configuration(): void
    {
        $email = $this->useFakeChannel('email');
        $sms = $this->useFakeChannel('sms');

        $this->enableChannel('email');

        $batchId = $this->createBatch();

        $this->publishReadings([
            [
                'batch_id' => $batchId,
                'thermal_deviation' => 4.2,
                'detected_at' => '2026-01-05T09:30:00+00:00',
            ],
        ]);

        $this->artisan('alerts:monitor')->assertSuccessful();

        $this->assertSame(1, $email->count());
        $this->assertSame(0, $sms->count());
    }

    public function test_it_returns_the_whole_history_and_filters_it_by_batch(): void
    {
        $firstBatchId = $this->createBatch();
        $secondBatchId = $this->createBatch('Vacuna combinada trivalente');

        $this->publishReadings([
            [
                'batch_id' => $secondBatchId,
                'sphereon_anomaly' => 7.5,
                'detected_at' => '2026-01-05T11:45:00+00:00',
            ],
            [
                'batch_id' => $firstBatchId,
                'thermal_deviation' => 4.2,
                'detected_at' => '2026-01-05T09:30:00+00:00',
            ],
        ]);

        $this->artisan('alerts:monitor')->assertSuccessful();

        $this->actingAsUser('Supervisor de Planta');

        $history = $this->getJson('/api/v1/alerts/history')->assertOk();

        $history->assertJsonCount(2, 'alerts');
        $history->assertJsonPath('alerts.0.batch_id', $firstBatchId);
        $history->assertJsonPath('alerts.1.batch_id', $secondBatchId);

        $filtered = $this->getJson("/api/v1/alerts/history?batch_id={$secondBatchId}")->assertOk();

        $filtered->assertJsonCount(1, 'alerts');
        $filtered->assertJsonPath('alerts.0.batch_id', $secondBatchId);
        $filtered->assertJsonPath('alerts.0.alert_type', AlertType::SPHEREON_ANOMALY);

        $listing = $this->getJson('/api/v1/alerts')->assertOk();

        $listing->assertJsonCount(2, 'alerts');

        $batchAlerts = $this->getJson("/api/v1/batches/{$firstBatchId}/alerts")->assertOk();

        $batchAlerts->assertJsonCount(1, 'alerts');
        $batchAlerts->assertJsonPath('alerts.0.batch_id', $firstBatchId);
    }

    public function test_it_requires_authentication_to_consult_the_alert_history(): void
    {
        $this->getJson('/api/v1/alerts/history')->assertUnauthorized();
    }

    /**
     * Sustituye un canal de notificación por un doble en memoria.
     */
    private function useFakeChannel(string $channel): FakeAlertNotifier
    {
        $notifier = new FakeAlertNotifier($channel);

        $this->app->instance("alert-notifier.{$channel}", $notifier);

        return $notifier;
    }

    /**
     * Habilita un canal de notificación en la configuración de monitorización.
     */
    private function enableChannel(string $channel): void
    {
        config([
            "monitoring.notification.{$channel}.enabled" => true,
            "monitoring.notification.{$channel}.recipients" => [$channel.'@vaccines.local'],
        ]);
    }

    /**
     * Crea un lote en la fase de cultivo y devuelve su identificador.
     */
    private function createBatch(string $referenceName = 'Vacuna conjugada intramuscular'): string
    {
        $this->actingAsUser('Operador de Producción');

        $response = $this->postJson('/api/v1/batches', [
            'reference_id' => $this->vaccineReferenceId($referenceName),
        ]);

        $response->assertCreated();

        return (string) $response->json('id');
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
