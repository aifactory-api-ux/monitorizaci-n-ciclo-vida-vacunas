<?php

declare(strict_types=1);

namespace App\Api\Http\Controllers\V1;

use App\Api\Http\Resources\AlertResource;
use App\Application\Alerts\GetAlertHistory;
use App\Application\Alerts\ListAlerts;
use App\Domain\Alert\Entity\Alert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints de consulta de las alertas operativas.
 *
 * Ninguno de estos endpoints genera alertas: las alertas se generan de forma
 * automática al evaluar las lecturas de la monitorización y aquí sólo se
 * consultan.
 */
class AlertController
{
    public function __construct(
        private readonly ListAlerts $listAlerts,
        private readonly GetAlertHistory $getAlertHistory,
    ) {}

    /**
     * Listado de las alertas registradas.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'alerts' => $this->toArray($this->listAlerts->execute()),
        ]);
    }

    /**
     * Histórico de las alertas generadas.
     *
     * Admite acotar el histórico a un lote con el parámetro `batch_id`.
     */
    public function history(Request $request): JsonResponse
    {
        $batchId = $request->query('batch_id');
        $alerts = $this->getAlertHistory->execute(
            is_string($batchId) && $batchId !== '' ? $batchId : null,
        );

        return response()->json([
            'alerts' => $this->toArray($alerts),
        ]);
    }

    /**
     * Alertas del lote indicado.
     */
    public function forBatch(string $batchId): JsonResponse
    {
        return response()->json([
            'alerts' => $this->toArray($this->listAlerts->execute($batchId)),
        ]);
    }

    /**
     * Serializa las alertas de la respuesta.
     *
     * @param  list<Alert>  $alerts
     * @return list<array<string, mixed>>
     */
    private function toArray(array $alerts): array
    {
        return array_map(
            fn (Alert $alert): array => AlertResource::make($alert)->resolve(),
            $alerts,
        );
    }
}
