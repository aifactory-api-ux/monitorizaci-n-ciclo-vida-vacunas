<?php

declare(strict_types=1);

namespace App\Domain\Backup\Port;

use App\Domain\Backup\ValueObject\CacheEntry;

/**
 * Puerto de la instantánea del almacén de claves.
 *
 * La instantánea conserva el contenido del almacén de Redis con el nombre
 * real de cada clave y su tiempo de vida, de modo que la restauración reponga
 * el estado previo del caché y del backend de difusión (véase D19).
 */
interface CacheSnapshotStore
{
    /**
     * Entradas presentes en el almacén de claves.
     *
     * @return list<CacheEntry>
     */
    public function entries(): array;

    /**
     * Sustituye el contenido del almacén de claves por las entradas indicadas.
     *
     * @param  list<CacheEntry>  $entries
     */
    public function replace(array $entries): void;
}
