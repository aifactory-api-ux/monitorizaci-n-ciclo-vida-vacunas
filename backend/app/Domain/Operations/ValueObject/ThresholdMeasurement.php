<?php

declare(strict_types=1);

namespace App\Domain\Operations\ValueObject;

/**
 * Medición de un umbral no funcional del sistema.
 *
 * Asocia el criterio de aceptación que exige el umbral con el indicador
 * observado, la unidad de medida, el valor medido y el valor que no puede
 * superarse. Las duraciones se comparan por debajo del límite y los códigos
 * de respuesta por igualdad (véase NFR001, NFR003, NFR006 y NFR008).
 */
final class ThresholdMeasurement
{
    /**
     * El valor medido debe ser inferior al umbral.
     */
    public const COMPARISON_BELOW = 'below';

    /**
     * El valor medido debe coincidir con el valor esperado.
     */
    public const COMPARISON_EQUAL = 'equal';

    private function __construct(
        private readonly string $criterion,
        private readonly string $indicator,
        private readonly string $unit,
        private readonly float $measured,
        private readonly float $limit,
        private readonly string $comparison,
    ) {}

    /**
     * Medición de una duración que no puede superar el umbral.
     */
    public static function below(string $criterion, string $indicator, float $measured, float $limit, string $unit = 'segundos'): self
    {
        return new self($criterion, $indicator, $unit, $measured, $limit, self::COMPARISON_BELOW);
    }

    /**
     * Medición de un valor que debe coincidir con el esperado.
     */
    public static function equal(string $criterion, string $indicator, float $measured, float $expected, string $unit = ''): self
    {
        return new self($criterion, $indicator, $unit, $measured, $expected, self::COMPARISON_EQUAL);
    }

    /**
     * Criterio de aceptación al que corresponde la medición.
     */
    public function criterion(): string
    {
        return $this->criterion;
    }

    /**
     * Indicador observado en la medición.
     */
    public function indicator(): string
    {
        return $this->indicator;
    }

    /**
     * Unidad de medida del valor medido.
     */
    public function unit(): string
    {
        return $this->unit;
    }

    /**
     * Valor medido.
     */
    public function measured(): float
    {
        return $this->measured;
    }

    /**
     * Valor que el límite impone.
     */
    public function limit(): float
    {
        return $this->limit;
    }

    /**
     * Comparación aplicada entre el valor medido y el límite.
     */
    public function comparison(): string
    {
        return $this->comparison;
    }

    /**
     * Indica si la medición cumple el umbral.
     */
    public function isSatisfied(): bool
    {
        return match ($this->comparison) {
            self::COMPARISON_BELOW => $this->measured < $this->limit,
            self::COMPARISON_EQUAL => $this->measured === $this->limit,
            default => false,
        };
    }

    /**
     * Representación serializable de la medición.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'criterion' => $this->criterion,
            'indicator' => $this->indicator,
            'unit' => $this->unit,
            'measured' => $this->measured,
            'limit' => $this->limit,
            'comparison' => $this->comparison,
            'satisfied' => $this->isSatisfied(),
        ];
    }
}
