<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repository;

use App\Domain\VaccineBatch\Entity\PhaseTransition;
use App\Domain\VaccineBatch\Repository\PhaseTransitionRepository;
use App\Infrastructure\Persistence\Eloquent\Model\PhaseTransitionModel;
use DateTimeImmutable;

/**
 * Adaptador Eloquent del puerto de persistencia de transiciones de fase.
 *
 * Sólo expone la alta: el repositorio no ofrece ninguna operación de
 * actualización ni de borrado, de modo que el registro es inmutable (véase D4).
 */
final class EloquentPhaseTransitionRepository implements PhaseTransitionRepository
{
    public function append(PhaseTransition $transition): void
    {
        PhaseTransitionModel::query()->create([
            'id' => $transition->id(),
            'batch_id' => $transition->batchId(),
            'from_phase' => $transition->fromPhase()->value(),
            'to_phase' => $transition->toPhase()->value(),
            'transition_date' => $transition->transitionDate(),
        ]);
    }

    public function findByBatchId(string $batchId): array
    {
        return PhaseTransitionModel::query()
            ->where('batch_id', $batchId)
            ->orderBy('transition_date')
            ->orderBy('id')
            ->get()
            ->map(fn (PhaseTransitionModel $model): PhaseTransition => $this->toEntity($model))
            ->values()
            ->all();
    }

    /**
     * Recupera todas las transiciones registradas, ordenadas
     * cronológicamente.
     *
     * @return list<PhaseTransition>
     */
    public function findAll(): array
    {
        return PhaseTransitionModel::query()
            ->orderBy('transition_date')
            ->orderBy('id')
            ->get()
            ->map(fn (PhaseTransitionModel $model): PhaseTransition => $this->toEntity($model))
            ->values()
            ->all();
    }

    /**
     * Reconstruye la transición a partir del modelo persistido.
     */
    private function toEntity(PhaseTransitionModel $model): PhaseTransition
    {
        return PhaseTransition::reconstitute(
            (string) $model->getKey(),
            (string) $model->batch_id,
            (string) $model->from_phase,
            (string) $model->to_phase,
            $this->toImmutable($model->transition_date),
        );
    }

    /**
     * Convierte la marca temporal del modelo en una fecha inmutable.
     */
    private function toImmutable(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        return new DateTimeImmutable((string) $value);
    }
}
