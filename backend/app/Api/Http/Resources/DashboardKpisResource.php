<?php

declare(strict_types=1);

namespace App\Api\Http\Resources;

use App\Domain\Dashboard\ValueObject\CycleTimeReport;
use App\Domain\Dashboard\ValueObject\PhaseCycleTime;
use App\Domain\Dashboard\ValueObject\StatusBreakdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación de los indicadores del panel.
 *
 * Publica los tiempos de ciclo de cada fase y el peso de cada estado de
 * lote sobre el total (véase D21).
 *
 * @mixin CycleTimeReport
 */
class DashboardKpisResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'cycle_time' => [
                'by_phase' => array_map(
                    fn (PhaseCycleTime $cycleTime): array => $this->phaseCycleTime($cycleTime),
                    $this->resource->byPhase(),
                ),
                'total_batches' => $this->resource->totalBatches(),
            ],
            'status_breakdown' => array_map(
                fn (StatusBreakdown $breakdown): array => $this->statusBreakdown($breakdown),
                $this->resource->statusBreakdown(),
            ),
        ];
    }

    /**
     * Tiempo de ciclo de una fase.
     *
     * @return array<string, mixed>
     */
    private function phaseCycleTime(PhaseCycleTime $cycleTime): array
    {
        return [
            'phase' => $cycleTime->phase(),
            'average_seconds' => $cycleTime->averageSeconds(),
            'average_hours' => $cycleTime->averageHours(),
            'sample_size' => $cycleTime->sampleSize(),
            'in_progress_count' => $cycleTime->inProgressCount(),
        ];
    }

    /**
     * Peso de un estado de lote.
     *
     * @return array<string, mixed>
     */
    private function statusBreakdown(StatusBreakdown $breakdown): array
    {
        return [
            'status' => $breakdown->status(),
            'count' => $breakdown->count(),
            'percentage' => $breakdown->percentage(),
        ];
    }
}
