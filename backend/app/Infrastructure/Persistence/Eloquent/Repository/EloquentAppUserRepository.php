<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repository;

use App\Domain\User\Entity\AppUser;
use App\Domain\User\Repository\AppUserRepository;
use App\Domain\User\ValueObject\UserRole;
use App\Infrastructure\Persistence\Eloquent\Model\AppUserModel;

/**
 * Adaptador Eloquent del puerto de consulta de usuarios de la aplicación.
 */
final class EloquentAppUserRepository implements AppUserRepository
{
    public function findById(string $id): ?AppUser
    {
        $model = AppUserModel::query()->find($id);

        return $model === null ? null : $this->toEntity($model);
    }

    /**
     * Reconstruye el usuario a partir del modelo persistido.
     */
    private function toEntity(AppUserModel $model): AppUser
    {
        return AppUser::reconstitute(
            (string) $model->getKey(),
            (string) $model->name,
            (string) $model->email,
            UserRole::from((string) $model->role),
        );
    }
}
