<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Service;

use App\Domain\Dashboard\ValueObject\CycleTimeReport;
use App\Domain\Dashboard\ValueObject\PhaseCycleTime;
use App\Domain\Dashboard\ValueObject\StatusBreakdown;
use App\Domain\VaccineBatch\Entity\PhaseTransition;
use App\Domain\VaccineBatch\Entity\VaccineBatch;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;
use App\Domain\VaccineBatch\ValueObject\BatchStatus;

/**
 * Calculadora de los tiempos de ciclo del lote de vacuna.
 *
 * El tiempo de ciclo de una fase es el tiempo que el lote permanece en ella,
 * desde que entra hasta que la abandona. Sólo las fases completadas, es
 * decir, aquellas de las que existe una transición de salida, componen la
 * duración media; los lotes que siguen en la fase se contabilizan aparte
 * como lotes en curso (véase D21).
 */
final class CycleTimeCalculator
{
    /**
     * Calcula los indicadores de tiempos de ciclo y de desglose de estados.
     *
     * @param  list<VaccineBatch>  $batches
     * @param  list<PhaseTransition>  $transitions
     */
    public function calculate(array $batches, array $transitions): CycleTimeReport
    {
        $transitionsByBatch = $this->groupTransitionsByBatch($transitions);

        /** @var array<string, list<float>> $durations */
        $durations = [];

        /** @var array<string, int> $inProgress */
        $inProgress = [];

        foreach ($batches as $batch) {
            $currentPhase = $batch->phase()->value();
            $inProgress[$currentPhase] = ($inProgress[$currentPhase] ?? 0) + 1;

            $enteredAt = $batch->createdAt();

            foreach ($transitionsByBatch[$batch->id()] ?? [] as $transition) {
                $exitedAt = $transition->transitionDate();

                // El tramo corresponde a la fase de la que sale el lote.
                $phase = $transition->fromPhase()->value();

                $durations[$phase][] = max(
                    0,
                    $exitedAt->getTimestamp() - $enteredAt->getTimestamp(),
                );

                $enteredAt = $exitedAt;
            }
        }

        return new CycleTimeReport(
            $this->phaseCycleTimes($durations, $inProgress),
            $this->statusBreakdown($batches),
            count($batches),
        );
    }

    /**
     * Agrupa las transiciones por lote y las ordena cronológicamente.
     *
     * @param  list<PhaseTransition>  $transitions
     * @return array<string, list<PhaseTransition>>
     */
    private function groupTransitionsByBatch(array $transitions): array
    {
        $grouped = [];

        foreach ($transitions as $transition) {
            $grouped[$transition->batchId()][] = $transition;
        }

        foreach ($grouped as $batchId => $batchTransitions) {
            usort(
                $batchTransitions,
                static fn (PhaseTransition $first, PhaseTransition $second): int => $first->transitionDate() <=> $second->transitionDate(),
            );

            $grouped[$batchId] = $batchTransitions;
        }

        return $grouped;
    }

    /**
     * Tiempo de ciclo de cada fase del ciclo de vida.
     *
     * @param  array<string, list<float>>  $durations
     * @param  array<string, int>  $inProgress
     * @return list<PhaseCycleTime>
     */
    private function phaseCycleTimes(array $durations, array $inProgress): array
    {
        $cycleTimes = [];

        foreach (BatchPhase::values() as $phase) {
            $samples = $durations[$phase] ?? [];

            $cycleTimes[] = new PhaseCycleTime(
                $phase,
                $samples === [] ? null : round(array_sum($samples) / count($samples), 2),
                count($samples),
                $inProgress[$phase] ?? 0,
            );
        }

        return $cycleTimes;
    }

    /**
     * Peso de cada estado de lote sobre el total de lotes registrados.
     *
     * @param  list<VaccineBatch>  $batches
     * @return list<StatusBreakdown>
     */
    private function statusBreakdown(array $batches): array
    {
        $total = count($batches);

        /** @var array<string, int> $counts */
        $counts = [];

        foreach ($batches as $batch) {
            $status = $batch->status()->value();
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }

        $breakdown = [];

        foreach (BatchStatus::values() as $status) {
            $count = $counts[$status] ?? 0;

            if ($count === 0) {
                continue;
            }

            $breakdown[] = new StatusBreakdown(
                $status,
                $count,
                $total === 0 ? 0.0 : round($count / $total * 100, 2),
            );
        }

        return $breakdown;
    }
}
