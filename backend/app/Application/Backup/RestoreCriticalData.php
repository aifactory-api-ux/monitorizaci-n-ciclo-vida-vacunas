<?php

declare(strict_types=1);

namespace App\Application\Backup;

use App\Domain\Backup\Exception\BackupIntegrityException;
use App\Domain\Backup\Port\BackupArtifactStore;
use App\Domain\Backup\Port\CacheSnapshotStore;
use App\Domain\Backup\Port\CriticalDataStore;
use App\Domain\Backup\ValueObject\BackupManifest;
use App\Domain\Backup\ValueObject\CacheEntry;
use JsonException;

/**
 * Caso de uso de restauración de los datos críticos.
 *
 * La restauración verifica el resumen SHA-256 de cada fichero del artefacto
 * antes de aplicar ningún dato: un artefacto alterado o incompleto se
 * rechaza y el almacén de destino queda intacto. Una vez verificada la
 * integridad, las filas de las tablas críticas se incorporan al almacén de
 * datos y las claves se repone en el almacén de claves, de modo que el
 * sistema vuelve a operar con normalidad (véase D19).
 */
final class RestoreCriticalData
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
     * Restaura el artefacto indicado y devuelve el resultado de la operación.
     *
     * @param  string|null  $artifactId  Identificador del artefacto; si se omite,
     *                                   se restaura el más reciente.
     *
     * @throws BackupIntegrityException Si el artefacto no existe o no es íntegro.
     */
    public function execute(?string $artifactId = null): RestoreReport
    {
        $artifactId = $artifactId === null || $artifactId === '' ? $this->artifacts->latestArtifactId() : $artifactId;

        if ($artifactId === null) {
            throw BackupIntegrityException::unknownArtifact('');
        }

        if (! $this->artifacts->has($artifactId)) {
            throw BackupIntegrityException::unknownArtifact($artifactId);
        }

        $manifest = BackupManifest::fromArray($this->decode(
            $this->artifacts->get($artifactId, BackupManifest::MANIFEST_FILE),
        ));

        $tables = $this->decode($this->verified($artifactId, $manifest->databaseFile(), $manifest->databaseChecksum()));
        $entries = $this->decode($this->verified($artifactId, $manifest->cacheFile(), $manifest->cacheChecksum()));

        $rows = $this->tableRows($tables);
        $keys = $this->cacheEntries($entries);

        $this->criticalData->importTables($rows);
        $this->cacheSnapshot->replace($keys);

        return new RestoreReport(
            $manifest->artifactId(),
            array_sum(array_map('count', $rows)),
            count($keys),
        );
    }

    /**
     * Lee un fichero del artefacto y comprueba su resumen declarado.
     *
     * @throws BackupIntegrityException Si el fichero falta o su resumen no coincide.
     */
    private function verified(string $artifactId, string $file, string $expectedChecksum): string
    {
        $contents = $this->artifacts->get($artifactId, $file);

        $checksum = hash('sha256', $contents);

        if (! hash_equals($expectedChecksum, $checksum)) {
            throw BackupIntegrityException::checksumMismatch($file, $expectedChecksum, $checksum);
        }

        return $contents;
    }

    /**
     * Filas de las tablas críticas contenidas en el volcado.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, list<array<string, mixed>>>
     *
     * @throws BackupIntegrityException Si el volcado no tiene la forma esperada.
     */
    private function tableRows(array $data): array
    {
        $tables = $data['tables'] ?? null;

        if (! is_array($tables)) {
            throw BackupIntegrityException::malformedManifest('el volcado de datos no declara las tablas.');
        }

        $rows = [];

        foreach ($tables as $table => $tableRows) {
            if (! is_string($table) || ! is_array($tableRows)) {
                throw BackupIntegrityException::malformedManifest('el volcado de datos contiene una tabla no válida.');
            }

            foreach ($tableRows as $row) {
                if (! is_array($row)) {
                    throw BackupIntegrityException::malformedManifest(sprintf('el volcado de la tabla "%s" contiene una fila no válida.', $table));
                }
            }

            $rows[$table] = array_values($tableRows);
        }

        return $rows;
    }

    /**
     * Entradas del almacén de claves contenidas en la instantánea.
     *
     * @param  array<string, mixed>  $data
     * @return list<CacheEntry>
     *
     * @throws BackupIntegrityException Si la instantánea no tiene la forma esperada.
     */
    private function cacheEntries(array $data): array
    {
        $entries = $data['entries'] ?? null;

        if (! is_array($entries)) {
            throw BackupIntegrityException::malformedManifest('la instantánea de claves no declara las entradas.');
        }

        $snapshot = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)
                || ! is_string($entry['key'] ?? null)
                || ! is_string($entry['payload'] ?? null)
                || ! is_int($entry['ttl_ms'] ?? null)) {
                throw BackupIntegrityException::malformedManifest('la instantánea de claves contiene una entrada no válida.');
            }

            $decoded = base64_decode($entry['payload'], true);

            if ($decoded === false) {
                throw BackupIntegrityException::malformedManifest(sprintf('el valor de la clave "%s" no es un contenido válido.', $entry['key']));
            }

            $snapshot[] = new CacheEntry($entry['key'], $decoded, $entry['ttl_ms']);
        }

        return $snapshot;
    }

    /**
     * Decodifica un contenido JSON del artefacto.
     *
     * @return array<string, mixed>
     *
     * @throws BackupIntegrityException Si el contenido no es un JSON válido.
     */
    private function decode(string $contents): array
    {
        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new BackupIntegrityException(
                sprintf('El artefacto de respaldo no contiene un JSON válido: %s', $exception->getMessage()),
                BackupIntegrityException::ERROR_CODE,
                $exception,
            );
        }

        if (! is_array($decoded)) {
            throw BackupIntegrityException::malformedManifest('el contenido del artefacto no es un objeto JSON.');
        }

        return $decoded;
    }
}
