<?php

declare(strict_types=1);

namespace App\Application\Operations;

use App\Application\Dashboard\GetDashboardKpis;
use App\Application\Search\BatchSearchService;
use App\Domain\Operations\Event\OperationalProbe;
use App\Domain\Operations\Port\MonitoringTarget;
use App\Domain\Operations\Port\ProtectedRouteProbe;
use App\Domain\Operations\ValueObject\NonFunctionalThresholdReport;
use App\Domain\Operations\ValueObject\ThresholdMeasurement;
use App\Domain\Search\ValueObject\BatchSearchCriteria;
use App\Domain\Shared\Event\DomainEventRecorder;

/**
 * Caso de uso de medición de los umbrales no funcionales exigidos.
 *
 * Mide la carga de los indicadores del panel, el percentil 95 de la
 * búsqueda avanzada, la latencia de publicación de los eventos en el canal
 * de tiempo real y la respuesta de las rutas protegidas sin token. El
 * informe que devuelve determina si el despliegue cumple los umbrales
 * (véase NFR001, NFR003, NFR006 y NFR008).
 */
final class MeasureNonFunctionalThresholds
{
    /**
     * @param  float  $dashboardLimitSeconds  Umbral de carga del panel.
     * @param  float  $searchP95LimitSeconds  Umbral del percentil 95 de la búsqueda.
     * @param  float  $eventLatencyLimitSeconds  Umbral de latencia de los eventos.
     * @param  int  $protectedStatusCode  Código de estado exigido sin token.
     * @param  int  $searchSamples  Número de muestras del percentil 95.
     */
    public function __construct(
        private readonly MonitoringTarget $target,
        private readonly ProtectedRouteProbe $protectedRoute,
        private readonly GetDashboardKpis $dashboardKpis,
        private readonly BatchSearchService $search,
        private readonly DomainEventRecorder $events,
        private readonly float $dashboardLimitSeconds,
        private readonly float $searchP95LimitSeconds,
        private readonly float $eventLatencyLimitSeconds,
        private readonly int $protectedStatusCode = 401,
        private readonly int $searchSamples = 20,
    ) {}

    /**
     * Mide los umbrales y devuelve el informe con su cumplimiento.
     */
    public function execute(): NonFunctionalThresholdReport
    {
        return new NonFunctionalThresholdReport([
            ThresholdMeasurement::below(
                'NFR001',
                'carga de los indicadores del panel',
                $this->measureDashboard(),
                $this->dashboardLimitSeconds,
            ),
            ThresholdMeasurement::below(
                'NFR008',
                'percentil 95 de la búsqueda avanzada',
                $this->measureSearchP95(),
                $this->searchP95LimitSeconds,
            ),
            ThresholdMeasurement::below(
                'NFR006',
                'latencia de publicación de los eventos en el canal',
                $this->measureEventLatency(),
                $this->eventLatencyLimitSeconds,
            ),
            ThresholdMeasurement::equal(
                'NFR003',
                'respuesta de las rutas protegidas sin token',
                (float) $this->protectedRoute->statusCode(),
                (float) $this->protectedStatusCode,
                'código de estado',
            ),
        ]);
    }

    /**
     * Tiempo que tarda el panel en obtener los indicadores de su tiempo de
     * ciclo y de los estados de los lotes.
     */
    private function measureDashboard(): float
    {
        return $this->target->measure(fn () => $this->dashboardKpis->execute());
    }

    /**
     * Percentil 95 del tiempo que tarda la búsqueda avanzada.
     */
    private function measureSearchP95(): float
    {
        $durations = [];

        for ($sample = 1; $sample <= $this->searchSamples; $sample++) {
            // Cada muestra consulta una página distinta, de modo que ninguna
            // de ellas se sirve desde la caché de resultados.
            $durations[] = $this->target->measure(fn (): mixed => $this->search->execute(
                BatchSearchCriteria::create(page: $sample, perPage: 25),
            ));
        }

        sort($durations);

        $index = (int) ceil(0.95 * count($durations)) - 1;

        return $durations[max($index, 0)];
    }

    /**
     * Tiempo que tarda un evento en publicarse en el canal de tiempo real.
     */
    private function measureEventLatency(): float
    {
        return $this->target->measure(fn (): mixed => $this->events->record(new OperationalProbe([
            'dashboard_limit_seconds' => $this->dashboardLimitSeconds,
            'search_p95_limit_seconds' => $this->searchP95LimitSeconds,
            'event_latency_limit_seconds' => $this->eventLatencyLimitSeconds,
        ])));
    }
}
