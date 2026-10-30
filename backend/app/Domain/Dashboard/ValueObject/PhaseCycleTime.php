<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\ValueObject;

/**
 * Tiempo de ciclo de una fase del lote de vacuna.
 *
 * El tiempo de ciclo se calcula sobre las fases completadas: el tiempo que
 * el lote permanece en la fase desde que entra en ella hasta que la
 * abandona. La duración media es nula cuando todavía no hay ninguna fase
 * completada de ese tipo.
 */
final class PhaseCycleTime
{
    /**
     * @param  float|null  $averageSeconds  Duración media en segundos, nula si no hay muestras.
     */
    public function __construct(
        private readonly string $phase,
        private readonly ?float $averageSeconds,
        private readonly int $sampleSize,
        private readonly int $inProgressCount,
    ) {}

    /**
     * Literal de la fase.
     */
    public function phase(): string
    {
        return $this->phase;
    }

    /**
     * Duración media de la fase en segundos, nula si no hay muestras.
     */
    public function averageSeconds(): ?float
    {
        return $this->averageSeconds;
    }

    /**
     * Duración media de la fase en horas, nula si no hay muestras.
     */
    public function averageHours(): ?float
    {
        if ($this->averageSeconds === null) {
            return null;
        }

        return round($this->averageSeconds / 3600, 4);
    }

    /**
     * Número de fases completadas que componen la media.
     */
    public function sampleSize(): int
    {
        return $this->sampleSize;
    }

    /**
     * Número de lotes que están actualmente en la fase.
     */
    public function inProgressCount(): int
    {
        return $this->inProgressCount;
    }
}
