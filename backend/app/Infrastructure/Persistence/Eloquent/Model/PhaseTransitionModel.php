<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Model;

/**
 * Modelo de la tabla `PHASE_TRANSITION`.
 *
 * La tabla no tiene columna `updated_at`: sus filas son inmutables.
 */
class PhaseTransitionModel extends UuidModel
{
    /**
     * Tabla del contrato de esquema.
     *
     * @var string
     */
    protected $table = 'PHASE_TRANSITION';

    /**
     * Clave primaria UUID.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Columna con la fecha de la transición.
     *
     * @var string
     */
    public const CREATED_AT = 'transition_date';

    /**
     * Las transiciones no se modifican.
     *
     * @var null
     */
    public const UPDATED_AT = null;

    /**
     * Asignaciones de tipo de la tabla.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'transition_date' => 'datetime',
    ];

    /**
     * Atributos asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'batch_id',
        'from_phase',
        'to_phase',
        'transition_date',
    ];
}
