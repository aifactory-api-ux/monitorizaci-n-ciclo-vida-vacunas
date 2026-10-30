<?php

declare(strict_types=1);

namespace Tests\Feature\Realtime;

use App\Application\Realtime\MeasureRealtimeCapacity;
use App\Domain\Operations\Event\OperationalProbe;
use App\Domain\Realtime\Port\RealtimeSubscriberRegistry;
use App\Domain\Realtime\ValueObject\RealtimeCapacityReport;
use App\Domain\Shared\Event\DomainEventRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Doubles\FailingRealtimeSubscriber;
use Tests\TestCase;

/**
 * Capacidad del canal de tiempo real: el canal sostiene miles de conexiones
 * simultáneas y entrega las actualizaciones sin degradación perceptible
 * (véase D11 y NFR009).
 */
class RealtimeCapacityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Número mínimo de conexiones simultáneas que debe sostener el canal.
     */
    private const MINIMUM_CONNECTIONS = 1000;

    /**
     * Latencia máxima de entrega admitida, en segundos.
     */
    private const LATENCY_LIMIT_SECONDS = 1.0;

    public function test_it_delivers_the_update_to_thousands_of_concurrent_connections(): void
    {
        $channel = (string) config('operations.realtime.channel');
        $subscribers = (int) config('operations.realtime.subscribers');

        $this->assertGreaterThanOrEqual(
            self::MINIMUM_CONNECTIONS,
            $subscribers,
            'La verificación de la capacidad del canal exige miles de conexiones simultáneas.',
        );

        $capacity = $this->app->make(MeasureRealtimeCapacity::class);

        $this->assertSame($subscribers, $capacity->connect($channel, $subscribers));

        $this->app->make(DomainEventRecorder::class)->record(new OperationalProbe(['probe' => true]));

        $report = $capacity->report($channel);

        $this->assertSame($subscribers, $report->subscribers());
        $this->assertSame($subscribers, $report->delivered());
        $this->assertSame(0, $report->failures());
        $this->assertTrue($report->hasNoDegradation());
    }

    public function test_it_delivers_the_update_without_exceeding_the_latency_limit(): void
    {
        $channel = (string) config('operations.realtime.channel');
        $subscribers = (int) config('operations.realtime.subscribers');

        $capacity = $this->app->make(MeasureRealtimeCapacity::class);
        $capacity->connect($channel, $subscribers);

        $this->app->make(DomainEventRecorder::class)->record(new OperationalProbe(['probe' => true]));

        $report = $capacity->report($channel);

        $this->assertTrue(
            $report->isWithinLimit(self::LATENCY_LIMIT_SECONDS),
            sprintf(
                'La entrega a %d conexiones ha tardado %.3f segundos.',
                $report->subscribers(),
                $report->maxLatencySeconds(),
            ),
        );
    }

    public function test_it_accounts_for_the_connections_that_fail_during_the_delivery(): void
    {
        $channel = (string) config('operations.realtime.channel');
        $registry = $this->app->make(RealtimeSubscriberRegistry::class);

        $registry->subscribe($channel, new FailingRealtimeSubscriber);
        $registry->subscribe($channel, new FailingRealtimeSubscriber);

        $registry->deliver($channel, 'batch.created', ['id' => 'lote']);

        $report = $registry->report($channel);

        $this->assertSame(2, $report->subscribers());
        $this->assertSame(0, $report->delivered());
        $this->assertSame(2, $report->failures());
        $this->assertFalse($report->hasNoDegradation());
    }

    public function test_it_reports_the_capacity_of_the_channel(): void
    {
        $channel = (string) config('operations.realtime.channel');

        $capacity = $this->app->make(MeasureRealtimeCapacity::class);
        $capacity->connect($channel, 25);

        $this->app->make(DomainEventRecorder::class)->record(new OperationalProbe(['probe' => true]));

        $report = $capacity->report($channel);

        $this->assertInstanceOf(RealtimeCapacityReport::class, $report);
        $this->assertSame(25, $report->subscribers());
        $this->assertSame(25, $report->delivered());
    }
}
