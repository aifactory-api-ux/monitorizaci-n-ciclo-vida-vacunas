<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Model;

/**
 * Modelo de la tabla `ALERT`.
 *
 * La tabla del contrato de esquema no registra fecha de actualización: sólo
 * conserva la fecha y hora de la detección de la alerta.
 */
class AlertModel extends UuidModel
{
    /**
     * Tabla del contrato de esquema.
     *
     * @var string
     */
    protected $table = 'ALERT';

    /**
     * Clave primaria UUID.
     *
     * @var string
     */
    protected $primaryKey = 'id';

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
        'created_at' => 'datetime',
    ];

    /**
     * Atributos asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'batch_id',
        'alert_type',
        'severity',
        'message',
        'created_at',
    ];
}
