<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Model;

use App\Domain\Shared\ValueObject\Uuid;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * Modelo de la tabla `APP_USER`.
 *
 * Es el usuario autenticado por Passport que emite y valida los tokens
 * Bearer JWT de la API (véase D9).
 */
class AppUserModel extends Authenticatable
{
    use HasApiTokens;

    /**
     * Tabla del contrato de esquema.
     *
     * @var string
     */
    protected $table = 'APP_USER';

    /**
     * Clave primaria UUID.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Las claves primarias UUID no se autogeneran en base de datos.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Tipo de la clave primaria.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Asignaciones de tipo de la tabla.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'password' => 'hashed',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Atributos asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * Genera la clave primaria UUID del usuario.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $user): void {
            if (blank($user->getKey())) {
                $user->setAttribute($user->getKeyName(), Uuid::v4());
            }
        });
    }
}
