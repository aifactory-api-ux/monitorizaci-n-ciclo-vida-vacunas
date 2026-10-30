<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repository;

use App\Domain\VaccineBatch\Entity\VaccineBatch;
use App\Domain\VaccineBatch\Repository\VaccineBatchRepository;
use App\Infrastructure\Persistence\Eloquent\Model\VaccineBatchModel;
use DateTimeImmutable;

/**
 * Adaptador Eloquent del puerto de persistencia del lote.
 */
final class EloquentVaccineBatchRepository implements VaccineBatchRepository
{
    public function save(VaccineBatch $batch): void
    {
        $model = VaccineBatchModel::query()->firstOrNew(['id' => $batch->id()]);

        $model->reference_id = $batch->referenceId();
        $model->phase = $batch->phase()->value();
        $model->status = $batch->status()->value();
        $model->current_campus_id = $batch->currentCampusId();
        $model->created_at = $batch->createdAt();
        $model->updated_at = $batch->updatedAt();

        $model->save();
    }

    public function findById(string $batchId): ?VaccineBatch
    {
        $model = VaccineBatchModel::query()->find($batchId);

        if ($model === null) {
            return null;
        }

        return VaccineBatch::reconstitute(
            $model->getKey(),
            (string) $model->reference_id,
            (string) $model->phase,
            (string) $model->status,
            (string) $model->current_campus_id,
            $this->toImmutable($model->created_at),
            $this->toImmutable($model->updated_at),
        );
    }

    /**
     * Recupera todos los lotes registrados, del más reciente al más antiguo.
     *
     * @return list<VaccineBatch>
     */
    public function findAll(): array
    {
        return VaccineBatchModel::query()
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (VaccineBatchModel $model): VaccineBatch => VaccineBatch::reconstitute(
                $model->getKey(),
                (string) $model->reference_id,
                (string) $model->phase,
                (string) $model->status,
                (string) $model->current_campus_id,
                $this->toImmutable($model->created_at),
                $this->toImmutable($model->updated_at),
            ))
            ->values()
            ->all();
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
