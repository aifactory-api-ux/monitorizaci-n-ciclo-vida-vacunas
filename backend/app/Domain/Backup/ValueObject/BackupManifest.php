<?php

declare(strict_types=1);

namespace App\Domain\Backup\ValueObject;

use App\Domain\Backup\Exception\BackupIntegrityException;
use DateTimeImmutable;
use Throwable;

/**
 * Manifiesto de un artefacto de respaldo.
 *
 * Declara el identificador del artefacto, el instante de su creación, el
 * resumen SHA-256 de cada fichero y el número de filas o de claves que cada
 * fichero contiene. La restauración verifica los resúmenes antes de aplicar
 * ningún dato, de modo que un artefacto alterado se rechaza en lugar de
 * dejar el almacén de destino inconsistente (véase D19).
 */
final class BackupManifest
{
    /**
     * Nombre del fichero con el manifiesto del artefacto.
     */
    public const MANIFEST_FILE = 'manifest.json';

    /**
     * Nombre del fichero con el volcado de los datos críticos.
     */
    public const DATABASE_FILE = 'database.json';

    /**
     * Nombre del fichero con la instantánea del almacén de claves.
     */
    public const CACHE_FILE = 'cache.json';

    /**
     * @param  array<string, int>  $tableCounts  Número de filas por tabla.
     */
    public function __construct(
        private readonly string $artifactId,
        private readonly DateTimeImmutable $createdAt,
        private readonly string $databaseFile,
        private readonly string $databaseChecksum,
        private readonly array $tableCounts,
        private readonly string $cacheFile,
        private readonly string $cacheChecksum,
        private readonly int $cacheEntries,
    ) {}

    /**
     * Identificador del artefacto, ordenable en el tiempo.
     */
    public function artifactId(): string
    {
        return $this->artifactId;
    }

    /**
     * Instante en que se generó el artefacto.
     */
    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Nombre del fichero con el volcado de los datos críticos.
     */
    public function databaseFile(): string
    {
        return $this->databaseFile;
    }

    /**
     * Resumen SHA-256 declarado del volcado de los datos críticos.
     */
    public function databaseChecksum(): string
    {
        return $this->databaseChecksum;
    }

    /**
     * Número de filas respaldadas por tabla.
     *
     * @return array<string, int>
     */
    public function tableCounts(): array
    {
        return $this->tableCounts;
    }

    /**
     * Nombre del fichero con la instantánea del almacén de claves.
     */
    public function cacheFile(): string
    {
        return $this->cacheFile;
    }

    /**
     * Resumen SHA-256 declarado de la instantánea del almacén de claves.
     */
    public function cacheChecksum(): string
    {
        return $this->cacheChecksum;
    }

    /**
     * Número de claves respaldadas del almacén de claves.
     */
    public function cacheEntries(): int
    {
        return $this->cacheEntries;
    }

    /**
     * Representación serializable del manifiesto.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'artifact_id' => $this->artifactId,
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'database' => [
                'file' => $this->databaseFile,
                'checksum' => $this->databaseChecksum,
                'tables' => $this->tableCounts,
            ],
            'cache' => [
                'file' => $this->cacheFile,
                'checksum' => $this->cacheChecksum,
                'entries' => $this->cacheEntries,
            ],
        ];
    }

    /**
     * Reconstruye el manifiesto a partir de su representación serializable.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws BackupIntegrityException Si el manifiesto no tiene la forma esperada.
     */
    public static function fromArray(array $data): self
    {
        $artifactId = $data['artifact_id'] ?? null;
        $createdAt = $data['created_at'] ?? null;
        $database = $data['database'] ?? null;
        $cache = $data['cache'] ?? null;

        if (! is_string($artifactId) || $artifactId === '') {
            throw BackupIntegrityException::malformedManifest('no declara el identificador del artefacto.');
        }

        if (! is_string($createdAt) || $createdAt === '') {
            throw BackupIntegrityException::malformedManifest('no declara la fecha de creación.');
        }

        if (! is_array($database) || ! is_array($cache)) {
            throw BackupIntegrityException::malformedManifest('no declara el volcado de datos ni la instantánea de claves.');
        }

        $databaseFile = $database['file'] ?? null;
        $databaseChecksum = $database['checksum'] ?? null;
        $cacheFile = $cache['file'] ?? null;
        $cacheChecksum = $cache['checksum'] ?? null;
        $cacheEntries = $cache['entries'] ?? null;

        if (! is_string($databaseFile) || ! is_string($databaseChecksum)) {
            throw BackupIntegrityException::malformedManifest('no declara el fichero del volcado de datos ni su resumen.');
        }

        if (! is_string($cacheFile) || ! is_string($cacheChecksum) || ! is_int($cacheEntries)) {
            throw BackupIntegrityException::malformedManifest('no declara el fichero de la instantánea de claves ni su resumen.');
        }

        $tableCounts = [];

        foreach (($database['tables'] ?? []) as $table => $count) {
            if (! is_string($table) || ! is_int($count)) {
                throw BackupIntegrityException::malformedManifest('el número de filas por tabla no es válido.');
            }

            $tableCounts[$table] = $count;
        }

        try {
            $createdOn = new DateTimeImmutable($createdAt);
        } catch (Throwable) {
            throw BackupIntegrityException::malformedManifest('la fecha de creación no es una fecha válida.');
        }

        return new self(
            $artifactId,
            $createdOn,
            $databaseFile,
            $databaseChecksum,
            $tableCounts,
            $cacheFile,
            $cacheChecksum,
            $cacheEntries,
        );
    }
}
