<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Model;

use App\Domain\Shared\ValueObject\Uuid;

/**
 * Modelo de la tabla `CAMPUS`.
 */
class CampusModel extends UuidModel
{
    /**
     * Tabla del contrato de esquema.
     *
     * @var string
     */
    protected $table = 'CAMPUS';

    /**
     * Clave primaria UUID.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Asignaciones de tipo de la tabla.
     *
     * @var array<string, string>
     */
    protected $casts = [
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
    ];

    /**
     * Los registros de catálogo usan identificadores v4 (véase D3).
     */
    protected function newIdentifier(): string
    {
        return Uuid::v4();
    }
}
