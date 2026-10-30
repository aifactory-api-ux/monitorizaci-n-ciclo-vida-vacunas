<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Infrastructure\Persistence\Eloquent\Model\AppUserModel;
use Laravel\Passport\Passport;

/**
 * Peticiones autenticadas con un token Bearer JWT.
 */
trait MakesAuthenticatedRequests
{
    /**
     * Autentica la petición con un usuario del rol indicado.
     */
    protected function actingAsUser(string $role): AppUserModel
    {
        $user = $this->createUser($role);

        Passport::actingAs($user, [], 'api');

        return $user;
    }
}
