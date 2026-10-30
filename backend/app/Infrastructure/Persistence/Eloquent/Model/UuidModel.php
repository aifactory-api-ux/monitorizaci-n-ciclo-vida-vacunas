<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Model;

use App\Domain\Shared\ValueObject\Uuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\UuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo base de las tablas del contrato con clave primaria UUID.
 */
abstract class UuidModel extends Model
{
    use UuidPrimaryKey;

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
     * Genera un identificador v7, ordenable por tiempo.
     */
    protected function newIdentifier(): string
    {
        return Uuid::v7();
    }
}
