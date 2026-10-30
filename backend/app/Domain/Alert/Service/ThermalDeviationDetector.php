<?php

declare(strict_types=1);

namespace App\Domain\Alert\Service;

use App\Domain\Alert\Entity\Alert;
use DateTimeImmutable;

/**
 * Detector de desviación térmica sobre un lote.
 *
 * Compara la desviación térmica leída con el umbral configurado en
 * `config/monitoring.php`, que es parametrizable por entorno mientras se
 * concuerda el valor definitivo con el área de calidad.
 */
final class ThermalDeviationDetector
{
    /**
     * @param  float  $deviationThreshold  Umbral de desviación permitido, en grados centígrados.
     */
    public function __construct(
        private readonly AlertFactory $alerts,
        private readonly float $deviationThreshold,
    ) {}

    /**
     * Evalúa la lectura térmica del lote.
     *
     * @param  float  $deviation  Desviación térmica leída, en grados centígrados.
     * @return Alert|null La alerta de prioridad máxima o null si la lectura está en rango.
     */
    public function detect(string $batchId, float $deviation, ?DateTimeImmutable $detectedAt = null): ?Alert
    {
        if (abs($deviation) <= $this->deviationThreshold) {
            return null;
        }

        return $this->alerts->thermalDeviation($batchId, $deviation, $this->deviationThreshold, $detectedAt);
    }

    /**
     * Umbral de desviación configurado.
     */
    public function threshold(): float
    {
        return $this->deviationThreshold;
    }
}
