<?php

declare(strict_types=1);

namespace Tests\Feature\Realtime;

use App\Application\Alerts\RaiseAlert;
use App\Domain\Alert\Entity\Alert;
use App\Domain\Alert\Event\AlertRaised;
use App\Domain\Alert\ValueObject\AlertSeverity;
use App\Domain\Alert\ValueObject\AlertType;
use App\Domain\VaccineBatch\Entity\Campus;
use App\Domain\VaccineBatch\Event\BatchCreated;
use App\Domain\VaccineBatch\Event\BatchPhaseAdvanced;
use App\Domain\VaccineBatch\Event\LogisticsCycleClosed;
use App\Domain\VaccineBatch\Event\PhysicalReceptionRegistered;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;
use App\Domain\VaccineBatch\ValueObject\BatchStatus;
use App\Infrastructure\Broadcasting\ArrayBroadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\Support\InteractsWithDatabase;
use Tests\Support\InteractsWithLims;
use Tests\Support\MakesAuthenticatedRequests;
use Tests\TestCase;

/**
 * Difusión de los eventos de lote y de alerta por el canal privado de
 * monitorización (véase D11).
 *
 * Las pruebas comprueban que cada hecho consumado del ciclo de vida del lote
 * se difunde con su nombre de evento y con los datos que el frontend necesita
 * para resincronizar el estado, y que el canal sólo admite a los usuarios con
 * un token Bearer válido.
 */
class BatchEventBroadcastTest extends TestCase
{
    use InteractsWithDatabase;
    use InteractsWithLims;
    use MakesAuthenticatedRequests;
    use RefreshDatabase;

    public function test_el_alta_del_lote_difunde_el_evento_de_creacion(): void
    {
        $batchId = $this->createBatch();

        $broadcast = $this->broadcastFor(BatchCreated::EVENT_NAME);

        $this->assertSame(['private-batch-monitoring'], $broadcast['channels']);
        $this->assertSame($batchId, $broadcast['payload']['batch_id']);
        $this->assertSame(BatchPhase::CULTURE, $broadcast['payload']['phase']);
        $this->assertSame(BatchStatus::IN_PROGRESS, $broadcast['payload']['status']);
        $this->assertSame($this->campusId(Campus::CULTURE), $broadcast['payload']['current_campus_id']);
    }

    public function test_el_avance_de_fase_difunde_el_evento_con_el_lote_y_el_campus(): void
    {
        $batchId = $this->createBatch();

        $this->postJson("/api/v1/batches/{$batchId}/transitions", [
            'to_phase' => BatchPhase::SPHEREON,
        ])->assertOk();

        $broadcast = $this->broadcastFor(BatchPhaseAdvanced::EVENT_NAME);

        $this->assertSame(['private-batch-monitoring'], $broadcast['channels']);
        $this->assertSame($batchId, $broadcast['payload']['batch_id']);
        $this->assertSame(BatchPhase::CULTURE, $broadcast['payload']['from_phase']);
        $this->assertSame(BatchPhase::SPHEREON, $broadcast['payload']['to_phase']);
        $this->assertSame($this->campusId(Campus::SPHEREON), $broadcast['payload']['current_campus_id']);
    }

    public function test_la_recepcion_fisica_difunde_el_evento_con_el_estado_resultante(): void
    {
        $batchId = $this->createBatchInLogisticsPhase();

        $this->postJson("/api/v1/batches/{$batchId}/reception")->assertOk();

        $broadcast = $this->broadcastFor(PhysicalReceptionRegistered::EVENT_NAME);

        $this->assertSame(['private-batch-monitoring'], $broadcast['channels']);
        $this->assertSame($batchId, $broadcast['payload']['batch_id']);
        $this->assertSame(BatchStatus::RECEIVED, $broadcast['payload']['status']);
        $this->assertSame($this->campusId(Campus::LOGISTICS), $broadcast['payload']['current_campus_id']);
    }

    public function test_el_cierre_logistico_difunde_el_evento_con_el_estado_resultante(): void
    {
        $batchId = $this->createBatchInLogisticsPhase();

        $this->postJson("/api/v1/batches/{$batchId}/reception")->assertOk();
        $this->postJson("/api/v1/batches/{$batchId}/logistics-close")->assertOk();

        $broadcast = $this->broadcastFor(LogisticsCycleClosed::EVENT_NAME);

        $this->assertSame(['private-batch-monitoring'], $broadcast['channels']);
        $this->assertSame($batchId, $broadcast['payload']['batch_id']);
        $this->assertSame(BatchStatus::CLOSED, $broadcast['payload']['status']);
    }

    public function test_la_alerta_difunde_el_evento_con_el_lote_afectado(): void
    {
        $batchId = $this->createBatch();

        $this->app->make(RaiseAlert::class)->execute(
            Alert::raise(
                $batchId,
                AlertType::from(AlertType::THERMAL_DEVIATION),
                AlertSeverity::from(AlertSeverity::CRITICAL),
                'Desviación térmica de 4,2 grados centígrados sobre el lote.',
            ),
        );

        $broadcast = $this->broadcastFor(AlertRaised::EVENT_NAME);

        $this->assertSame(['private-batch-monitoring'], $broadcast['channels']);
        $this->assertSame($batchId, $broadcast['payload']['batch_id']);
        $this->assertSame(AlertType::THERMAL_DEVIATION, $broadcast['payload']['alert_type']);
        $this->assertSame(AlertSeverity::CRITICAL, $broadcast['payload']['severity']);
    }

    public function test_el_canal_privado_rechaza_la_suscripcion_sin_token(): void
    {
        $this->getJson('/broadcasting/auth?channel_name=private-batch-monitoring&socket_id=1234.5678')
            ->assertUnauthorized();
    }

    public function test_la_latencia_entre_el_origen_del_evento_y_su_entrega_no_supera_un_segundo(): void
    {
        $this->seedCampuses();
        $this->seedVaccineReferences();
        $this->actingAsUser('Operador de Producción');

        $origin = microtime(true);

        $this->postJson('/api/v1/batches', [
            'reference_id' => $this->vaccineReferenceId('Vacuna conjugada intramuscular'),
        ])->assertCreated();

        $broadcast = $this->broadcastFor(BatchCreated::EVENT_NAME);

        $latency = $broadcast['delivered_at'] - $origin;

        $this->assertGreaterThanOrEqual(
            0.0,
            $latency,
            'El evento no puede entregarse antes de originarse.',
        );

        $this->assertLessThan(
            1.0,
            $latency,
            sprintf(
                'La latencia entre el origen del evento y su entrega por el canal de tiempo real ha sido de %.3f segundos.',
                $latency,
            ),
        );
    }

    public function test_el_canal_privado_concede_la_suscripcion_con_token_valido(): void
    {
        $this->actingAsUser('Supervisor de Planta');

        $response = $this->get('/broadcasting/auth?channel_name=private-batch-monitoring&socket_id=1234.5678');

        $response->assertOk();
        $this->assertSame('true', $response->getContent());
    }

    /**
     * Crea un lote en la fase de cultivo y devuelve su identificador.
     */
    private function createBatch(): string
    {
        return $this->createBatchWithReference()['id'];
    }

    /**
     * Crea un lote en la fase de cultivo y devuelve su identificador y la
     * referencia de vacuna asociada.
     *
     * @return array{id: string, reference_id: string}
     */
    private function createBatchWithReference(): array
    {
        $this->seedCampuses();
        $this->seedVaccineReferences();
        $this->actingAsUser('Operador de Producción');

        $response = $this->postJson('/api/v1/batches', [
            'reference_id' => $this->vaccineReferenceId('Vacuna conjugada intramuscular'),
        ]);

        $response->assertCreated();

        return [
            'id' => (string) $response->json('id'),
            'reference_id' => (string) $response->json('reference_id'),
        ];
    }

    /**
     * Crea un lote ya situado en la fase logística del Campus 3.
     */
    private function createBatchInLogisticsPhase(): string
    {
        $batch = $this->createBatchWithReference();

        $this->postJson("/api/v1/batches/{$batch['id']}/transitions", [
            'to_phase' => BatchPhase::SPHEREON,
        ])->assertOk();

        // La fase logística exige la aprobación microbiológica de LIMS Labware.
        $this->importLimsApproval($batch['id'], $batch['reference_id']);

        $this->postJson("/api/v1/batches/{$batch['id']}/transitions", [
            'to_phase' => BatchPhase::LOGISTICS,
        ])->assertOk();

        return $batch['id'];
    }

    /**
     * Difusión registrada con el nombre de evento indicado.
     *
     * @return array{channels: list<string>, event: string, payload: array<string, mixed>, delivered_at: float}
     */
    private function broadcastFor(string $event): array
    {
        $broadcaster = Broadcast::connection();

        $this->assertInstanceOf(ArrayBroadcaster::class, $broadcaster);

        foreach ($broadcaster->broadcasts() as $broadcast) {
            if ($broadcast['event'] === $event) {
                return $broadcast;
            }
        }

        $this->fail(sprintf('No se ha difundir ningún evento "%s".', $event));
    }
}
