<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exception;

/**
 * Indica que la operación solicitada excede las capacidades del actor.
 *
 * La matriz de permisos por rol se aplica en la capa Application, que es la
 * única fuente de verdad; esta excepción transporta el motivo sin filtrar
 * detalles de infraestructura.
 */
class UnauthorizedOperationException extends DomainException
{
    /**
     * Intento de forzar la liberación de un lote sin aprobación microbiológica.
     */
    public static function forceGatingRelease(): self
    {
        return new self(
            'El Técnico de Control de Calidad tiene prohibido forzar la liberación de un lote (Gating) '
            .'si el sistema no ha recibido la aprobación microbiológica correspondiente de LIMS Labware.',
            'gating_release_forbidden',
        );
    }

    /**
     * Intento de consultar el historial de auditoría sin rol de auditoría.
     */
    public static function auditTrailAccess(): self
    {
        return new self(
            'El historial completo e inmutable de auditoría sólo está disponible para el rol de Auditor / Administrador IT.',
            'audit_trail_forbidden',
        );
    }

    /**
     * Operación que excede las capacidades del rol del actor.
     */
    public static function operationForbidden(string $role, string $operation): self
    {
        return new self(
            sprintf(
                'El rol "%s" no puede ejecutar la operación "%s".',
                $role,
                $operation,
            ),
            'operation_forbidden',
        );
    }
}
