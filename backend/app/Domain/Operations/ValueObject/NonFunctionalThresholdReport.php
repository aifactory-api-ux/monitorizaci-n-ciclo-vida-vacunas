<?php

declare(strict_types=1);

namespace App\Domain\Operations\ValueObject;

/**
 * Informe de los umbrales no funcionales medidos.
 *
 * Reúne las mediciones de los umbrales exigidos por el enunciado y determina
 * si todas cumplen. El informe es el resultado que registran el comando de
 * verificación y la etapa de aceptación (véase D21).
 */
final class NonFunctionalThresholdReport
{
    /**
     * @param  list<ThresholdMeasurement>  $measurements
     */
    public function __construct(private readonly array $measurements) {}

    /**
     * Mediciones que componen el informe.
     *
     * @return list<ThresholdMeasurement>
     */
    public function measurements(): array
    {
        return $this->measurements;
    }

    /**
     * Medición del criterio indicado.
     */
    public function measurementFor(string $criterion): ?ThresholdMeasurement
    {
        foreach ($this->measurements as $measurement) {
            if ($measurement->criterion() === $criterion) {
                return $measurement;
            }
        }

        return null;
    }

    /**
     * Indica si todas las mediciones cumplen su umbral.
     */
    public function isCompliant(): bool
    {
        return $this->violations() === [];
    }

    /**
     * Mediciones que incumplen su umbral.
     *
     * @return list<ThresholdMeasurement>
     */
    public function violations(): array
    {
        return array_values(array_filter(
            $this->measurements,
            static fn (ThresholdMeasurement $measurement): bool => ! $measurement->isSatisfied(),
        ));
    }

    /**
     * Representación serializable del informe.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'compliant' => $this->isCompliant(),
            'measurements' => array_map(
                static fn (ThresholdMeasurement $measurement): array => $measurement->toArray(),
                $this->measurements,
            ),
        ];
    }
}
