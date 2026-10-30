<?php

declare(strict_types=1);

namespace App\Api\Http\Controllers\V1;

use App\Api\Http\Resources\DashboardKpisResource;
use App\Application\Dashboard\GetDashboardKpis;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint de los indicadores del panel.
 */
class DashboardController
{
    public function __construct(
        private readonly GetDashboardKpis $dashboardKpis,
    ) {}

    /**
     * Tiempos de ciclo por fase y desglose de estados de los lotes.
     */
    public function index(): JsonResponse
    {
        $report = $this->dashboardKpis->execute();

        return response()->json([
            'kpis' => DashboardKpisResource::make($report)->resolve(),
        ]);
    }
}
