<?php

declare(strict_types=1);

namespace App\Domain\User\Repository;

use App\Domain\User\Entity\AppUser;

/**
 * Puerto de consulta de los usuarios de la aplicación.
 *
 * Permite a la capa de aplicación resolver el rol del actor que ejecuta una
 * operación sin depender del modelo de persistencia.
 */
interface AppUserRepository
{
    /**
     * Usuario con el identificador indicado, o null si no existe.
     */
    public function findById(string $id): ?AppUser;
}
