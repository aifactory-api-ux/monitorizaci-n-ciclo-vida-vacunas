<?php

declare(strict_types=1);

namespace App\Application\Alerts;

use App\Domain\Alert\Entity\Alert;
use App\Domain\Alert\Service\SphereonAnomalyDetector;
use App\Domain\Alert\Service\ThermalDeviationDetector;
use App\Domain\Shared\Exception\ResourceNotFoundException;
use App\Domain\VaccineBatch\Repository\VaccineBatchRepository;
use DateTimeImmutable;

/**
 * Caso de uso de evaluación de la monitorización de un lote.
 *
 * Evalúa las lecturas del lote con los detectores de desviación térmica y de
 * anomalía de formulación Sphereon y genera las alertas que correspondan. La
 * evaluación es automática: no existe ninguna petición de usuario que ordene
 * revisar las lecturas.
 */
final class EvaluateBatchMonitoring
{
    public function __construct(
        private readonly ThermalDeviationDetector $thermalDeviation,
        private readonly SphereonAnomalyDetector $sphereonAnomaly,
        private readonly RaiseAlert $raiseAlert,
        private readonly VaccineBatchRepository $batches,
    ) {}

    /**
     * Evalúa las lecturas del lote y genera las alertas detectadas.
     *
     * @param  float|null  $thermalDeviation  Desviación térmica leída, en grados centígrados.
     * @param  float|null  $sphereonAnomaly  Anomalía de formulación leída, en puntos porcentuales.
     * @return list<Alert> Alertas generadas por la evaluación.
     *
     * @throws ResourceNotFoundException Si el lote no existe.
     */
    public function execute(
        string $batchId,
        ?float $thermalDeviation = null,
        ?float $sphereonAnomaly = null,
        ?DateTimeImmutable $detectedAt = null,
    ): array {
        if ($this->batches->findById($batchId) === null) {
            throw ResourceNotFoundException::batch($batchId);
        }

        $alerts = [];

        if ($thermalDeviation !== null) {
            $alert = $this->thermalDeviation->detect($batchId, $thermalDeviation, $detectedAt);

            if ($alert !== null) {
                $alerts[] = $this->raiseAlert->execute($alert, $detectedAt);
            }
        }

        if ($sphereonAnomaly !== null) {
            $alert = $this->sphereonAnomaly->detect($batchId, $sphereonAnomaly, $detectedAt);

            if ($alert !== null) {
                $alerts[] = $this->raiseAlert->execute($alert, $detectedAt);
            }
        }

        return $alerts;
    }
}
