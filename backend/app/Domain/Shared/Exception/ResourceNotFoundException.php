<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exception;

/**
 * Recurso de dominio inexistente.
 *
 * La capa Api la traduce a una respuesta 404.
 */
class ResourceNotFoundException extends DomainException
{
    /**
     * Código de error estable para el cliente.
     */
    public const ERROR_CODE = 'resource_not_found';

    public function __construct(string $message, string $errorCode = self::ERROR_CODE)
    {
        parent::__construct($message, $errorCode);
    }

    /**
     * Lote inexistente.
     */
    public static function batch(string $batchId): self
    {
        return new self(
            sprintf('El lote "%s" no existe.', $batchId),
            'batch_not_found',
        );
    }

    /**
     * Evento de auditoría inexistente.
     */
    public static function auditEntry(string $entryId): self
    {
        return new self(
            sprintf('El evento de auditoría "%s" no existe.', $entryId),
            'audit_entry_not_found',
        );
    }
}
