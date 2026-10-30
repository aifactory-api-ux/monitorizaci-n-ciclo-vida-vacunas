<?php

declare(strict_types=1);

namespace App\Domain\Backup\ValueObject;

/**
 * Entrada de la instantánea del almacén de claves.
 *
 * Una entrada conserva el nombre real de la clave, su valor serializado tal y
 * como lo entrega el almacén y su tiempo de vida restante en milisegundos. Un
 * tiempo de vida de cero significa que la clave no caduca.
 */
final class CacheEntry
{
    public function __construct(
        private readonly string $key,
        private readonly string $payload,
        private readonly int $ttlMilliseconds,
    ) {}

    /**
     * Nombre de la clave en el almacén.
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * Valor serializado de la clave.
     */
    public function payload(): string
    {
        return $this->payload;
    }

    /**
     * Tiempo de vida restante en milisegundos, o cero si la clave no caduca.
     */
    public function ttlMilliseconds(): int
    {
        return $this->ttlMilliseconds;
    }

    /**
     * Indica si la entrada tiene fecha de caducidad.
     */
    public function hasExpiration(): bool
    {
        return $this->ttlMilliseconds > 0;
    }
}
