<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repository;

use App\Domain\VaccineBatch\Entity\Campus;
use App\Domain\VaccineBatch\Repository\CampusRepository;
use App\Infrastructure\Persistence\Eloquent\Model\CampusModel;

/**
 * Adaptador Eloquent del puerto de consulta del catálogo de campus.
 */
final class EloquentCampusRepository implements CampusRepository
{
    public function findById(string $campusId): ?Campus
    {
        $model = CampusModel::query()->find($campusId);

        return $model === null
            ? null
            : Campus::of((string) $model->getKey(), (string) $model->name);
    }

    public function findByName(string $campusName): ?Campus
    {
        $model = CampusModel::query()->where('name', $campusName)->first();

        return $model === null
            ? null
            : Campus::of((string) $model->getKey(), (string) $model->name);
    }
}
