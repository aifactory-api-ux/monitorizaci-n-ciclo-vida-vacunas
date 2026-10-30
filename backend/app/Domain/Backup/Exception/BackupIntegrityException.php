<?php

declare(strict_types=1);

namespace App\Domain\Backup\Exception;

use App\Domain\Shared\Exception\DomainException;

/**
 * Violación de la integridad de un artefacto de respaldo.
 *
 * La restauración no aplica ningún dato cuyo resumen no coincida con el
 * declarado en el manifiesto: un artefacto alterado o incompleto se rechaza
 * antes de tocar el almacén de destino (véase D19).
 */
final class BackupIntegrityException extends DomainException
{
    /**
     * Código de error estable de la violación de integridad.
     */
    public const ERROR_CODE = 'backup_integrity_violation';

    /**
     * Resumen de un fichero que no coincide con el declarado en el manifiesto.
     */
    public static function checksumMismatch(string $file, string $expected, string $actual): self
    {
        return new self(
            sprintf(
                'El fichero "%s" del artefacto de respaldo no es íntegro: su resumen SHA-256 es "%s" y el declarado en el manifiesto es "%s".',
                $file,
                $actual,
                $expected,
            ),
            self::ERROR_CODE,
        );
    }

    /**
     * Fichero ausente en el artefacto de respaldo.
     */
    public static function missingFile(string $file): self
    {
        return new self(
            sprintf('El artefacto de respaldo no contiene el fichero "%s".', $file),
            self::ERROR_CODE,
        );
    }

    /**
     * Manifiesto de respaldo con un contenido no válido.
     */
    public static function malformedManifest(string $reason): self
    {
        return new self(
            sprintf('El manifiesto del artefacto de respaldo no es válido: %s', $reason),
            self::ERROR_CODE,
        );
    }

    /**
     * Artefacto de respaldo inexistente en el almacén de artefactos.
     */
    public static function unknownArtifact(string $artifactId): self
    {
        return new self(
            sprintf('No existe ningún artefacto de respaldo con el identificador "%s".', $artifactId),
            self::ERROR_CODE,
        );
    }

    /**
     * Nombre de artefacto o de fichero no admisible.
     */
    public static function invalidName(string $name): self
    {
        return new self(
            sprintf('El nombre "%s" no es admisible en un artefacto de respaldo.', $name),
            self::ERROR_CODE,
        );
    }
}
