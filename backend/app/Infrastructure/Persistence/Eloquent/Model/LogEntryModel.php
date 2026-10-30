<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Model;

use App\Domain\Shared\Exception\DomainException;
use Illuminate\Database\Eloquent\Builder;

/**
 * Modelo de la tabla `LOG_ENTRY`.
 *
 * La tabla no tiene columna `updated_at`: sus entradas son inmutables y sólo
 * se agregan (véase D10).
 */
class LogEntryModel extends UuidModel
{
    /**
     * Tabla del contrato de esquema.
     *
     * @var string
     */
    protected $table = 'LOG_ENTRY';

    /**
     * Clave primaria UUID.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Las entradas del log no se modifican.
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
        'payload' => 'array',
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
        'user_id',
        'event_type',
        'payload',
        'created_at',
    ];

    /**
     * Las entradas existentes no admiten actualización (véase D10).
     *
     * @param  Builder<static>  $query
     */
    protected function performUpdate(Builder $query): void
    {
        throw DomainException::immutableLogEntry();
    }

    /**
     * Las entradas existentes no admiten borrado (véase D10).
     */
    protected function performDeleteOnModel(): void
    {
        throw DomainException::immutableLogEntry();
    }
}
