<?php

declare(strict_types=1);

namespace App\Domain\User\Entity;

use App\Domain\User\ValueObject\UserRole;

/**
 * Usuario de la aplicación con su rol asignado.
 */
final class AppUser
{
    private function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $email,
        private readonly UserRole $role,
    ) {}

    /**
     * Reconstruye el usuario a partir de su estado persistido.
     */
    public static function reconstitute(
        string $id,
        string $name,
        string $email,
        UserRole $role,
    ): self {
        return new self($id, $name, $email, $role);
    }

    /**
     * Identificador UUID del usuario.
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Nombre completo del usuario.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Correo electrónico del usuario.
     */
    public function email(): string
    {
        return $this->email;
    }

    /**
     * Rol asignado al usuario.
     */
    public function role(): UserRole
    {
        return $this->role;
    }
}
