<?php

declare(strict_types=1);

namespace App\Domain\Alert\Service;

use App\Domain\Alert\Entity\Alert;
use DateTimeImmutable;

/**
 * Detector de anomalía de formulación Sphereon sobre un lote.
 *
 * Compara la anomalía de formulación leída con el umbral configurado en
 * `config/monitoring.php`, que es parametrizable por entorno mientras se
 * concuerda el valor definitivo con el área de calidad.
 */
final class SphereonAnomalyDetector
{
    /**
     * @param  float  $anomalyThreshold  Umbral de anomalía permitido, en puntos porcentuales.
     */
    public function __construct(
        private readonly AlertFactory $alerts,
        private readonly float $anomalyThreshold,
    ) {}

    /**
     * Evalúa la lectura de formulación Sphereon del lote.
     *
     * @param  float  $anomaly  Anomalía leída, en puntos porcentuales.
     * @return Alert|null La alerta de prioridad máxima o null si la lectura está en rango.
     */
    public function detect(string $batchId, float $anomaly, ?DateTimeImmutable $detectedAt = null): ?Alert
    {
        if (abs($anomaly) <= $this->anomalyThreshold) {
            return null;
        }

        return $this->alerts->sphereonAnomaly($batchId, $anomaly, $this->anomalyThreshold, $detectedAt);
    }

    /**
     * Umbral de anomalía configurado.
     */
    public function threshold(): float
    {
        return $this->anomalyThreshold;
    }
}
