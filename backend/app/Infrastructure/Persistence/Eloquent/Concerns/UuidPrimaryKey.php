<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Concerns;

use App\Domain\Shared\ValueObject\Uuid;
use Illuminate\Database\Eloquent\Model;

/**
 * Asigna la clave primaria UUID de la tabla al crear el registro.
 *
 * Centraliza el uso del generador de identificadores (véase D3) para que
 * ningún modelo escriba su propia clave primaria.
 */
trait UuidPrimaryKey
{
    public static function bootUuidPrimaryKey(): void
    {
        static::creating(function (Model $model): void {
            if (blank($model->getKey())) {
                $model->setAttribute($model->getKeyName(), $model->newIdentifier());
            }
        });
    }

    /**
     * Genera el identificador de la clave primaria.
     */
    protected function newIdentifier(): string
    {
        return Uuid::v4();
    }
}
