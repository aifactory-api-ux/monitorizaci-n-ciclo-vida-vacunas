<?php

declare(strict_types=1);

namespace App\Domain\VaccineBatch\Service;

use App\Domain\Shared\Exception\DomainException;
use App\Domain\VaccineBatch\Entity\Campus;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;

/**
 * Política de transiciones de fase del lote.
 *
 * Centraliza la secuencia obligatoria de fases del ciclo de vida (véase D4):
 * un lote sólo avanza a la fase inmediatamente siguiente y nunca retrocede
 * ni repite fase.
 */
final class PhaseTransitionPolicy
{
    /**
     * Fase siguiente admitida para cada fase de origen.
     */
    private const ALLOWED_TRANSITIONS = [
        BatchPhase::CULTURE => BatchPhase::SPHEREON,
        BatchPhase::SPHEREON => BatchPhase::LOGISTICS,
    ];

    /**
     * Campus que aloja el lote en cada fase.
     */
    private const CAMPUS_BY_PHASE = [
        BatchPhase::CULTURE => Campus::CULTURE,
        BatchPhase::SPHEREON => Campus::SPHEREON,
        BatchPhase::LOGISTICS => Campus::LOGISTICS,
    ];

    /**
     * Indica si la transición está permitida.
     */
    public function canAdvance(BatchPhase $from, BatchPhase $to): bool
    {
        return (self::ALLOWED_TRANSITIONS[$from->value()] ?? null) === $to->value();
    }

    /**
     * Exige que la transición sea la siguiente de la secuencia.
     *
     * @throws DomainException Si la transición no es secuencial.
     */
    public function assertCanAdvance(BatchPhase $from, BatchPhase $to): void
    {
        if (! $this->canAdvance($from, $to)) {
            throw DomainException::nonSequentialTransition($from->value(), $to->value());
        }
    }

    /**
     * Fase siguiente de la secuencia, o null si la fase ya es la última.
     */
    public function nextPhase(BatchPhase $from): ?BatchPhase
    {
        $next = self::ALLOWED_TRANSITIONS[$from->value()] ?? null;

        return $next === null ? null : BatchPhase::from($next);
    }

    /**
     * Nombre del campus que aloja el lote en la fase dada.
     */
    public function campusNameFor(BatchPhase $phase): string
    {
        return self::CAMPUS_BY_PHASE[$phase->value()];
    }
}
