<?php

declare(strict_types=1);

namespace App\Domain\Alert\Service;

use App\Domain\Alert\Entity\Alert;
use App\Domain\Alert\ValueObject\AlertSeverity;
use App\Domain\Alert\ValueObject\AlertType;
use DateTimeImmutable;

/**
 * Fábrica de alertas operativas.
 *
 * Los dos tipos de prioridad máxima del contrato de negocio se generan con
 * severidad `Crítica`: la desviación térmica y la anomalía de formulación
 * Sphereon exigen la atención inmediata del Supervisor de Planta (véase TC005
 * y TC023).
 */
final class AlertFactory
{
    /**
     * Genera la alerta de desviación térmica de un lote.
     *
     * @param  float  $deviation  Desviación térmica detectada, en grados centígrados.
     * @param  float  $threshold  Umbral permitido, en grados centígrados.
     */
    public function thermalDeviation(
        string $batchId,
        float $deviation,
        float $threshold,
        ?DateTimeImmutable $detectedAt = null,
    ): Alert {
        return Alert::raise(
            $batchId,
            AlertType::from(AlertType::THERMAL_DEVIATION),
            AlertSeverity::from(AlertSeverity::CRITICAL),
            sprintf(
                'Desviación térmica de %.2f °C sobre el umbral permitido de %.2f °C.',
                $deviation,
                $threshold,
            ),
            $detectedAt,
        );
    }

    /**
     * Genera la alerta de anomalía de formulación Sphereon de un lote.
     *
     * @param  float  $anomaly  Anomalía detectada, en puntos porcentuales.
     * @param  float  $threshold  Umbral permitido, en puntos porcentuales.
     */
    public function sphereonAnomaly(
        string $batchId,
        float $anomaly,
        float $threshold,
        ?DateTimeImmutable $detectedAt = null,
    ): Alert {
        return Alert::raise(
            $batchId,
            AlertType::from(AlertType::SPHEREON_ANOMALY),
            AlertSeverity::from(AlertSeverity::CRITICAL),
            sprintf(
                'Anomalía de formulación Sphereon de %.2f puntos porcentuales sobre el umbral permitido de %.2f.',
                $anomaly,
                $threshold,
            ),
            $detectedAt,
        );
    }

    /**
     * Genera una alerta de cualquier tipo y severidad.
     *
     * Los tipos de prioridad no máxima se registran con una severidad distinta
     * de `Crítica` (véase el escenario de alertas de prioridad no máxima).
     */
    public function create(
        string $batchId,
        AlertType $alertType,
        AlertSeverity $severity,
        string $message,
        ?DateTimeImmutable $detectedAt = null,
    ): Alert {
        return Alert::raise($batchId, $alertType, $severity, $message, $detectedAt);
    }
}
