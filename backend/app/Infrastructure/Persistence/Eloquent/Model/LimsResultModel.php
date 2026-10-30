<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Model;

/**
 * Modelo de la tabla `LIMS_RESULT`.
 *
 * La tabla del contrato de esquema no tiene columnas de creación ni de
 * actualización: la trazabilidad de origen y fecha de los datos importados se
 * conserva en el log estructurado (véase D6).
 */
class LimsResultModel extends UuidModel
{
    /**
     * Tabla del contrato de esquema.
     *
     * @var string
     */
    protected $table = 'LIMS_RESULT';

    /**
     * Clave primaria UUID.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * La tabla no registra fecha de creación.
     *
     * @var null
     */
    public const CREATED_AT = null;

    /**
     * La tabla no registra fecha de actualización.
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
        'result_date' => 'datetime',
    ];

    /**
     * Atributos asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'batch_id',
        'test_type',
        'result',
        'approved_by',
        'result_date',
    ];
}
