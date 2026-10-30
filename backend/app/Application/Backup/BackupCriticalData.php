<?php

declare(strict_types=1);

namespace App\Application\Backup;

use App\Domain\Backup\Exception\BackupIntegrityException;
use App\Domain\Backup\Port\BackupArtifactStore;
use App\Domain\Backup\Port\CacheSnapshotStore;
use App\Domain\Backup\Port\CriticalDataStore;
use App\Domain\Backup\ValueObject\BackupManifest;
use App\Domain\Backup\ValueObject\CacheEntry;
use DateTimeImmutable;
use JsonException;

/**
 * Caso de uso de generación del respaldo de los datos críticos.
 *
 * El respaldo recoge las filas de las tablas críticas de MySQL y las claves
 * del almacén de Redis, las empaqueta en un artefacto portable con su
 * manifiesto y sus resúmenes SHA-256, y lo deposita en el almacén de
 * artefactos. El artefacto resultante es autocontenido: la restauración no
 * necesita el sistema de ficheros de origen ni el gestor de secretos (véase
 * D19).
 */
final class BackupCriticalData
{
    /**
     * Opciones de codificación del JSON de los artefactos.
     */
    private const JSON_OPTIONS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    public function __construct(
        private readonly CriticalDataStore $criticalData,
        private readonly CacheSnapshotStore $cacheSnapshot,
        private readonly BackupArtifactStore $artifacts,
    ) {}

    /**
     * Genera el artefacto de respaldo y devuelve su manifiesto.
     *
     * @param  string|null  $artifactId  Identificador del artefacto; si se omite,
     *                                   se genera uno ordenable en el tiempo.
     *
     * @throws BackupIntegrityException Si algún contenido no se puede codificar.
     */
    public function execute(?string $artifactId = null): BackupManifest
    {
        $artifactId = $artifactId === null || $artifactId === '' ? $this->newArtifactId() : $artifactId;

        $tables = $this->criticalData->exportTables();
        $entries = $this->cacheSnapshot->entries();

        $databaseContents = $this->encode(['tables' => $tables]);
        $cacheContents = $this->encode(['entries' => $this->cacheEntries($entries)]);

        $manifest = new BackupManifest(
            $artifactId,
            new DateTimeImmutable,
            BackupManifest::DATABASE_FILE,
            $this->checksum($databaseContents),
            $this->tableCounts($tables),
            BackupManifest::CACHE_FILE,
            $this->checksum($cacheContents),
            count($entries),
        );

        $this->artifacts->put($artifactId, BackupManifest::MANIFEST_FILE, $this->encode($manifest->toArray()));
        $this->artifacts->put($artifactId, BackupManifest::DATABASE_FILE, $databaseContents);
        $this->artifacts->put($artifactId, BackupManifest::CACHE_FILE, $cacheContents);

        return $manifest;
    }

    /**
     * Genera un identificador de artefacto ordenable en el tiempo.
     */
    private function newArtifactId(): string
    {
        return sprintf('%s-%s', gmdate('Ymd\THis\Z'), bin2hex(random_bytes(4)));
    }

    /**
     * Número de filas respaldadas por tabla.
     *
     * @param  array<string, list<array<string, mixed>>>  $tables
     * @return array<string, int>
     */
    private function tableCounts(array $tables): array
    {
        $counts = [];

        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }

    /**
     * Entradas del almacén de claves en su forma serializable.
     *
     * @param  list<CacheEntry>  $entries
     * @return list<array{key: string, payload: string, ttl_ms: int}>
     */
    private function cacheEntries(array $entries): array
    {
        $serialized = [];

        foreach ($entries as $entry) {
            $serialized[] = [
                'key' => $entry->key(),
                'payload' => base64_encode($entry->payload()),
                'ttl_ms' => $entry->ttlMilliseconds(),
            ];
        }

        return $serialized;
    }

    /**
     * Resume el contenido de un fichero del artefacto.
     */
    private function checksum(string $contents): string
    {
        return hash('sha256', $contents);
    }

    /**
     * Codifica un contenido como JSON.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws BackupIntegrityException Si el contenido no se puede codificar.
     */
    private function encode(array $data): string
    {
        try {
            return json_encode($data, self::JSON_OPTIONS);
        } catch (JsonException $exception) {
            throw new BackupIntegrityException(
                sprintf('No se ha podido codificar el contenido del respaldo: %s', $exception->getMessage()),
                BackupIntegrityException::ERROR_CODE,
                $exception,
            );
        }
    }
}
