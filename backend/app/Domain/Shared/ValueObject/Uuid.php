<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObject;

use App\Domain\Shared\Exception\DomainException;

/**
 * Identificador UUID de las claves primarias del contrato de esquema.
 *
 * Se generan dos versiones (véase D3): la v4 para los registros de catálogo
 * y la v7 para los registros con orden temporal natural, cuyo prefijo
 * temporal permite indexarlos de forma eficiente.
 */
final class Uuid
{
    /**
     * Formato canónico de un UUID con versión y variante válidas.
     */
    private const PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    /**
     * Longitud en bytes de un UUID.
     */
    private const BYTES = 16;

    /**
     * Genera un UUID v4 (aleatorio).
     */
    public static function v4(): string
    {
        $bytes = random_bytes(self::BYTES);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return self::format($bytes);
    }

    /**
     * Genera un UUID v7 (ordenado por tiempo).
     *
     * Los 48 bits iniciales contienen los milisegundos transcurridos desde
     * la época Unix, por lo que el orden lexicográfico de los identificadores
     * coincide con el orden temporal de creación.
     */
    public static function v7(): string
    {
        $bytes = random_bytes(self::BYTES);
        $milliseconds = (int) floor(microtime(true) * 1000);

        $bytes[0] = chr(($milliseconds >> 40) & 0xFF);
        $bytes[1] = chr(($milliseconds >> 32) & 0xFF);
        $bytes[2] = chr(($milliseconds >> 24) & 0xFF);
        $bytes[3] = chr(($milliseconds >> 16) & 0xFF);
        $bytes[4] = chr(($milliseconds >> 8) & 0xFF);
        $bytes[5] = chr($milliseconds & 0xFF);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x70);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return self::format($bytes);
    }

    /**
     * Indica si el valor tiene un formato UUID válido.
     */
    public static function isValid(string $value): bool
    {
        return preg_match(self::PATTERN, $value) === 1;
    }

    /**
     * Versión del UUID (4 o 7 para los identificadores generados aquí).
     *
     * @throws DomainException Si el valor no es un UUID válido.
     */
    public static function version(string $value): int
    {
        if (! self::isValid($value)) {
            throw DomainException::invalidUuid($value);
        }

        return (int) hexdec($value[14]);
    }

    /**
     * Da formato canónico a los bytes del identificador.
     */
    private static function format(string $bytes): string
    {
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
