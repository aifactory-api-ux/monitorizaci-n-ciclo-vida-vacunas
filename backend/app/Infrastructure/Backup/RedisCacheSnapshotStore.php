<?php

declare(strict_types=1);

namespace App\Infrastructure\Backup;

use App\Domain\Backup\Port\CacheSnapshotStore;
use App\Domain\Backup\ValueObject\CacheEntry;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Facades\Redis;

/**
 * Adaptador de la instantánea del almacén de claves sobre Redis.
 *
 * La instantánea se toma sobre la conexión declarada en `config/backup.php`,
 * que se abre sin prefijo de clave: el nombre real de cada clave se conserva
 * tal cual y la restauración repone exactamente las mismas claves, con su
 * contenido y su tiempo de vida. La sustitución es completa: las claves que
 * no forman parte de la instantánea se retiran del almacén, de modo que el
 * estado posterior a la restauración es el estado respaldado (véase D19).
 */
final class RedisCacheSnapshotStore implements CacheSnapshotStore
{
    /**
     * Conexión de Redis de la que se toma la instantánea.
     */
    private readonly string $connection;

    public function __construct(string $connection)
    {
        $this->connection = $connection;
    }

    public function entries(): array
    {
        $connection = $this->redis();

        $entries = [];

        foreach ($connection->keys('*') as $key) {
            $key = (string) $key;

            $payload = $connection->dump($key);

            if (! is_string($payload) || $payload === '') {
                continue;
            }

            $ttl = (int) $connection->pttl($key);

            $entries[] = new CacheEntry($key, $payload, $ttl > 0 ? $ttl : 0);
        }

        return $entries;
    }

    public function replace(array $entries): void
    {
        $connection = $this->redis();

        $restored = [];

        foreach ($entries as $entry) {
            $this->restore($connection, $entry);

            $restored[$entry->key()] = true;
        }

        foreach ($connection->keys('*') as $key) {
            $key = (string) $key;

            if (! isset($restored[$key])) {
                $connection->del($key);
            }
        }
    }

    /**
     * Repone una clave con su contenido y su tiempo de vida.
     */
    private function restore(Connection $connection, CacheEntry $entry): void
    {
        $ttl = $entry->ttlMilliseconds();

        if ($connection instanceof PhpRedisConnection) {
            $connection->restore($entry->key(), $ttl, $entry->payload(), ['REPLACE']);

            return;
        }

        $connection->restore($entry->key(), $ttl, $entry->payload(), 'REPLACE');
    }

    /**
     * Conexión de Redis de la instantánea.
     */
    private function redis(): Connection
    {
        return Redis::connection($this->connection);
    }
}
