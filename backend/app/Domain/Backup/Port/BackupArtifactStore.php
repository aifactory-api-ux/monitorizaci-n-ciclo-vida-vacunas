<?php

declare(strict_types=1);

namespace App\Domain\Backup\Port;

use App\Domain\Backup\Exception\BackupIntegrityException;

/**
 * Puerto del almacén de artefactos de respaldo.
 *
 * Un artefacto es un conjunto de ficheros identificados por un identificador
 * ordenable en el tiempo: el manifiesto, el volcado de los datos críticos y la
 * instantánea del almacén de claves (véase D19).
 */
interface BackupArtifactStore
{
    /**
     * Escribe un fichero del artefacto indicado.
     */
    public function put(string $artifactId, string $file, string $contents): void;

    /**
     * Lee un fichero del artefacto indicado.
     *
     * @throws BackupIntegrityException Si el fichero no existe.
     */
    public function get(string $artifactId, string $file): string;

    /**
     * Indica si el artefacto indicado existe en el almacén.
     */
    public function has(string $artifactId): bool;

    /**
     * Identificadores de los artefactos disponibles, del más antiguo al más
     * reciente.
     *
     * @return list<string>
     */
    public function artifacts(): array;

    /**
     * Identificador del artefacto más reciente, o null si no hay ninguno.
     */
    public function latestArtifactId(): ?string;
}
