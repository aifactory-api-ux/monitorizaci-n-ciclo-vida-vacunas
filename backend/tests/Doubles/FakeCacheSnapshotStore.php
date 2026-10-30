<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Domain\Backup\Port\CacheSnapshotStore;
use App\Domain\Backup\ValueObject\CacheEntry;

/**
 * Doble en memoria del almacén de claves.
 *
 * Sustituye al almacén de Redis durante las pruebas: guarda las claves en el
 * propio proceso, de modo que la suite no necesite un servidor de Redis para
 * verificar el respaldo y la restauración de la instantánea.
 */
final class FakeCacheSnapshotStore implements CacheSnapshotStore
{
    /**
     * Claves del almacén, indexadas por su nombre.
     *
     * @var array<string, CacheEntry>
     */
    private array $entries = [];

    /**
     * @param  list<CacheEntry>  $entries
     */
    public function __construct(array $entries = [])
    {
        foreach ($entries as $entry) {
            $this->entries[$entry->key()] = $entry;
        }
    }

    public function entries(): array
    {
        return array_values($this->entries);
    }

    public function replace(array $entries): void
    {
        $this->entries = [];

        foreach ($entries as $entry) {
            $this->entries[$entry->key()] = $entry;
        }
    }

    /**
     * Alta una clave en el almacén.
     */
    public function put(string $key, string $payload, int $ttlMilliseconds = 0): void
    {
        $this->entries[$key] = new CacheEntry($key, $payload, $ttlMilliseconds);
    }

    /**
     * Entrada almacenada con el nombre indicado, o null si no existe.
     */
    public function get(string $key): ?CacheEntry
    {
        return $this->entries[$key] ?? null;
    }

    /**
     * Número de claves almacenadas.
     */
    public function count(): int
    {
        return count($this->entries);
    }
}
