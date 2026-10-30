<?php

declare(strict_types=1);

namespace App\Application\Batches;

use App\Domain\Alert\Entity\Alert;
use App\Domain\Audit\Entity\LogEntry;
use App\Domain\Lims\Entity\LimsResult;
use App\Domain\VaccineBatch\Entity\PhaseTransition;
use App\Domain\VaccineBatch\Entity\VaccineBatch;
use App\Domain\VaccineBatch\ValueObject\CampusVisit;

/**
 * Trazabilidad completa de un lote de vacuna.
 *
 * Reúne el recorrido del lote entre campus, sus transiciones de fase, sus
 * resultados de LIMS, sus alertas y su historial de auditoría (TC003).
 */
final class BatchTraceability
{
    /**
     * @param  list<PhaseTransition>  $transitions
     * @param  list<LimsResult>  $limsResults
     * @param  list<Alert>  $alerts
     * @param  list<LogEntry>  $entries
     * @param  list<CampusVisit>  $campusSequence
     */
    public function __construct(
        private readonly VaccineBatch $batch,
        private readonly array $transitions,
        private readonly array $limsResults,
        private readonly array $alerts,
        private readonly array $entries,
        private readonly array $campusSequence,
    ) {}

    /**
     * Lote consultado.
     */
    public function batch(): VaccineBatch
    {
        return $this->batch;
    }

    /**
     * Transiciones de fase ordenadas cronológicamente.
     *
     * @return list<PhaseTransition>
     */
    public function transitions(): array
    {
        return $this->transitions;
    }

    /**
     * Resultados de LIMS asociados al lote.
     *
     * @return list<LimsResult>
     */
    public function limsResults(): array
    {
        return $this->limsResults;
    }

    /**
     * Alertas asociadas al lote.
     *
     * @return list<Alert>
     */
    public function alerts(): array
    {
        return $this->alerts;
    }

    /**
     * Historial de auditoría del lote.
     *
     * @return list<LogEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * Secuencia de campus recorrida por el lote.
     *
     * @return list<CampusVisit>
     */
    public function campusSequence(): array
    {
        return $this->campusSequence;
    }
}
