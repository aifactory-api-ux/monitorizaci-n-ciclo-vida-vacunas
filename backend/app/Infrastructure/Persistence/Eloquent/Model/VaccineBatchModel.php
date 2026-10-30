<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Model;

/**
 * Modelo de la tabla `VACCINE_BATCH`.
 */
class VaccineBatchModel extends UuidModel
{
    /**
     * Tabla del contrato de esquema.
     *
     * @var string
     */
    protected $table = 'VACCINE_BATCH';

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
        'reference_id',
        'phase',
        'status',
        'current_campus_id',
    ];
}
