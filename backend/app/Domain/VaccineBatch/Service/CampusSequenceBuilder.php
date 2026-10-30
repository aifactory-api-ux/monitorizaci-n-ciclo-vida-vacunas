<?php

declare(strict_types=1);

namespace App\Domain\VaccineBatch\Service;

use App\Domain\Shared\Exception\ConfigurationException;
use App\Domain\VaccineBatch\Entity\Campus;
use App\Domain\VaccineBatch\Entity\PhaseTransition;
use App\Domain\VaccineBatch\Entity\VaccineBatch;
use App\Domain\VaccineBatch\Repository\CampusRepository;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;
use App\Domain\VaccineBatch\ValueObject\CampusVisit;

/**
 * Construcción de la secuencia de campus recorrida por un lote.
 *
 * La secuencia comienza siempre en la fase de cultivo del Campus 1 y continúa
 * con cada transición registrada, de forma que la trazabilidad entre campus
 * queda completa y ordenada cronológicamente (TC003).
 */
final class CampusSequenceBuilder
{
    public function __construct(
        private readonly CampusRepository $campuses,
        private readonly PhaseTransitionPolicy $transitionPolicy,
    ) {}

    /**
     * Secuencia de campus del lote, ordenada cronológicamente.
     *
     * @param  list<PhaseTransition>  $transitions
     * @return list<CampusVisit>
     *
     * @throws ConfigurationException Si algún campus del recorrido no está dado de alta.
     */
    public function build(VaccineBatch $batch, array $transitions): array
    {
        $culture = BatchPhase::from(BatchPhase::CULTURE);

        $visits = [
            CampusVisit::of(
                $culture,
                $this->campusIdFor($culture),
                $this->transitionPolicy->campusNameFor($culture),
                $batch->createdAt(),
            ),
        ];

        foreach ($transitions as $transition) {
            $phase = $transition->toPhase();

            $visits[] = CampusVisit::of(
                $phase,
                $this->campusIdFor($phase),
                $this->transitionPolicy->campusNameFor($phase),
                $transition->transitionDate(),
            );
        }

        return $visits;
    }

    /**
     * Identificador del campus asociado a la fase.
     *
     * @throws ConfigurationException Si el campus no está dado de alta en el catálogo.
     */
    private function campusIdFor(BatchPhase $phase): string
    {
        $campusName = $this->transitionPolicy->campusNameFor($phase);

        $campus = $this->campuses->findByName($campusName);

        if (! $campus instanceof Campus) {
            throw ConfigurationException::missingCampus($campusName);
        }

        return $campus->id();
    }
}
