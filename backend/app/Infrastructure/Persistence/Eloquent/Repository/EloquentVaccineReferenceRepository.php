<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repository;

use App\Domain\VaccineBatch\Entity\VaccineReference;
use App\Domain\VaccineBatch\Repository\VaccineReferenceRepository;
use App\Infrastructure\Persistence\Eloquent\Model\VaccineReferenceModel;

/**
 * Adaptador Eloquent del puerto de consulta del catálogo de referencias.
 */
final class EloquentVaccineReferenceRepository implements VaccineReferenceRepository
{
    public function findById(string $referenceId): ?VaccineReference
    {
        $model = VaccineReferenceModel::query()->find($referenceId);

        return $model === null
            ? null
            : VaccineReference::of(
                (string) $model->getKey(),
                (string) $model->name,
                (string) $model->family,
            );
    }
}
