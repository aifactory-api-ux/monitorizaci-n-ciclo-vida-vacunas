<?php

declare(strict_types=1);

namespace App\Infrastructure\Backup;

use App\Domain\Backup\Exception\BackupIntegrityException;
use App\Domain\Backup\Port\BackupArtifactStore;
use RuntimeException;

/**
 * Adaptador del almacén de artefactos sobre el sistema de ficheros.
 *
 * Cada artefacto es un directorio con el manifiesto, el volcado de los datos
 * críticos y la instantánea del almacén de claves. Los nombres de artefacto
 * y de fichero se validan antes de tocar el disco, de modo que un artefacto
 * no puede escribir fuera del directorio raíz (véase D19).
 */
final class FilesystemBackupArtifactStore implements BackupArtifactStore
{
    /**
     * Formato admitido en los nombres de artefacto y de fichero.
     */
    private const SAFE_NAME = '/^[A-Za-z0-9._-]+$/';

    /**
     * Directorio raíz donde se depositan los artefactos.
     */
    private readonly string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim($root, DIRECTORY_SEPARATOR);
    }

    public function put(string $artifactId, string $file, string $contents): void
    {
        $directory = $this->directory($artifactId);

        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('No se ha podido crear el directorio del artefacto "%s".', $artifactId));
        }

        if (file_put_contents($directory.DIRECTORY_SEPARATOR.$file, $contents) === false) {
            throw new RuntimeException(sprintf('No se ha podido escribir el fichero "%s" del artefacto "%s".', $file, $artifactId));
        }
    }

    public function get(string $artifactId, string $file): string
    {
        $path = $this->directory($artifactId).DIRECTORY_SEPARATOR.$this->assertSafeName($file);

        if (! is_file($path) || ! is_readable($path)) {
            throw BackupIntegrityException::missingFile($file);
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw BackupIntegrityException::missingFile($file);
        }

        return $contents;
    }

    public function has(string $artifactId): bool
    {
        return is_dir($this->directory($artifactId));
    }

    public function artifacts(): array
    {
        if (! is_dir($this->root)) {
            return [];
        }

        $entries = scandir($this->root);

        if ($entries === false) {
            return [];
        }

        $artifacts = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (! is_dir($this->root.DIRECTORY_SEPARATOR.$entry)) {
                continue;
            }

            $artifacts[] = $entry;
        }

        sort($artifacts);

        return $artifacts;
    }

    public function latestArtifactId(): ?string
    {
        $artifacts = $this->artifacts();

        if ($artifacts === []) {
            return null;
        }

        return $artifacts[count($artifacts) - 1];
    }

    /**
     * Directorio del artefacto indicado.
     */
    private function directory(string $artifactId): string
    {
        return $this->root.DIRECTORY_SEPARATOR.$this->assertSafeName($artifactId);
    }

    /**
     * Valida que el nombre no permita salir del directorio raíz.
     *
     * @throws BackupIntegrityException Si el nombre no es admisible.
     */
    private function assertSafeName(string $name): string
    {
        if (preg_match(self::SAFE_NAME, $name) !== 1) {
            throw BackupIntegrityException::invalidName($name);
        }

        return $name;
    }
}
