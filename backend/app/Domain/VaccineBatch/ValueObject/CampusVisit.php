<?php

declare(strict_types=1);

namespace App\Domain\VaccineBatch\ValueObject;

use DateTimeImmutable;

/**
 * Visita de un lote a un campus durante una fase de su ciclo de vida.
 *
 * Una visita describe la permanencia del lote en el campus asociado a una
 * fase concreta, desde el momento en que entró en ella.
 */
final class CampusVisit
{
    private function __construct(
        private readonly BatchPhase $phase,
        private readonly string $campusId,
        private readonly string $campusName,
        private readonly DateTimeImmutable $since,
    ) {}

    /**
     * Crea la visita del lote a un campus.
     */
    public static function of(
        BatchPhase $phase,
        string $campusId,
        string $campusName,
        DateTimeImmutable $since,
    ): self {
        return new self($phase, $campusId, $campusName, $since);
    }

    /**
     * Fase en la que se encuentra el lote durante la visita.
     */
    public function phase(): BatchPhase
    {
        return $this->phase;
    }

    /**
     * Identificador del campus visitado.
     */
    public function campusId(): string
    {
        return $this->campusId;
    }

    /**
     * Nombre del campus visitado.
     */
    public function campusName(): string
    {
        return $this->campusName;
    }

    /**
     * Fecha y hora de entrada en el campus.
     */
    public function since(): DateTimeImmutable
    {
        return $this->since;
    }
}
