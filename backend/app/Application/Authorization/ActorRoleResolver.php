<?php

declare(strict_types=1);

namespace App\Application\Authorization;

use App\Domain\Shared\Exception\UnauthorizedOperationException;
use App\Domain\User\Entity\AppUser;
use App\Domain\User\Repository\AppUserRepository;
use App\Domain\User\ValueObject\UserRole;

/**
 * Resuelve el rol del actor autenticado a partir de su identificador.
 *
 * Centraliza la consulta del catálogo de usuarios para que las políticas de
 * autorización no dependan del modelo de persistencia (véase D9).
 */
final class ActorRoleResolver
{
    public function __construct(
        private readonly AppUserRepository $users,
    ) {}

    /**
     * Rol del actor indicado.
     *
     * @throws UnauthorizedOperationException Si el actor no existe o no tiene un rol válido.
     */
    public function resolve(?string $actorId): UserRole
    {
        if ($actorId === null || $actorId === '') {
            throw UnauthorizedOperationException::operationForbidden('desconocido', 'identify');
        }

        $actor = $this->users->findById($actorId);

        if (! $actor instanceof AppUser) {
            throw UnauthorizedOperationException::operationForbidden($actorId, 'identify');
        }

        return $actor->role();
    }
}
