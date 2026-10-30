<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exception;

use RuntimeException;
use Throwable;

/**
 * Violación de una regla de negocio del dominio.
 *
 * La capa Api traduce estas excepciones a la respuesta HTTP documentada
 * (422), nunca a un error interno del servidor.
 */
class DomainException extends RuntimeException
{
    /**
     * Código de error genérico de regla de negocio.
     */
    public const ERROR_CODE = 'domain_rule_violation';

    public function __construct(
        string $message,
        private readonly string $errorCode = self::ERROR_CODE,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Código de error estable para el cliente.
     */
    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Literal de fase desconocido.
     */
    public static function unknownPhase(string $literal): self
    {
        return new self(
            sprintf('La fase "%s" no es una fase válida del ciclo de vida del lote.', $literal),
            'unknown_phase',
        );
    }

    /**
     * Literal de estado desconocido.
     */
    public static function unknownStatus(string $literal): self
    {
        return new self(
            sprintf('El estado "%s" no es un estado válido del lote.', $literal),
            'unknown_status',
        );
    }

    /**
     * Transición de fase que no respeta la secuencia permitida.
     */
    public static function nonSequentialTransition(string $from, string $to): self
    {
        return new self(
            sprintf(
                'La transición de "%s" a "%s" no es secuencial: sólo se admite el avance a la fase siguiente.',
                $from,
                $to,
            ),
            'non_sequential_transition',
        );
    }

    /**
     * Referencia de vacuna que no existe en el catálogo.
     */
    public static function unknownReference(string $referenceId): self
    {
        return new self(
            sprintf('La referencia de vacuna "%s" no existe en el catálogo.', $referenceId),
            'unknown_vaccine_reference',
        );
    }

    /**
     * Lote que no admite la operación solicitada en su estado actual.
     */
    public static function invalidBatchState(string $message, string $errorCode = 'invalid_batch_state'): self
    {
        return new self($message, $errorCode);
    }

    /**
     * Avance a la fase logística sin aprobación microbiológica de LIMS Labware.
     */
    public static function gatingNotApproved(string $reason): self
    {
        return new self(
            sprintf('El lote no puede avanzar a la fase logística: %s', $reason),
            'gating_not_approved',
        );
    }

    /**
     * Literal de tipo de alerta desconocido.
     */
    public static function unknownAlertType(string $literal): self
    {
        return new self(
            sprintf('El tipo de alerta "%s" no es un tipo válido.', $literal),
            'unknown_alert_type',
        );
    }

    /**
     * Literal de severidad de alerta desconocido.
     */
    public static function unknownAlertSeverity(string $literal): self
    {
        return new self(
            sprintf('La severidad "%s" no es una severidad válida de alerta.', $literal),
            'unknown_alert_severity',
        );
    }

    /**
     * Literal de estado de alerta de lote desconocido.
     */
    public static function unknownAlertStatus(string $literal): self
    {
        return new self(
            sprintf('El estado de alerta "%s" no es un estado válido.', $literal),
            'unknown_alert_status',
        );
    }

    /**
     * Criterios de búsqueda que no admiten la ejecución de la consulta.
     */
    public static function invalidSearchCriteria(string $message): self
    {
        return new self($message, 'invalid_search_criteria');
    }

    /**
     * Identificador UUID con formato inválido.
     */
    public static function invalidUuid(string $value): self
    {
        return new self(
            sprintf('El identificador "%s" no tiene un formato UUID válido.', $value),
            'invalid_uuid',
        );
    }

    /**
     * Intento de modificar o eliminar una entrada del log de auditoría.
     */
    public static function immutableLogEntry(): self
    {
        return new self(
            'Las entradas del log de auditoría son inmutables: no admiten modificaciones ni eliminaciones.',
            'immutable_log_entry',
        );
    }

    /**
     * Tipo de evento de auditoría no reconocido por el esquema de la carga útil.
     */
    public static function unknownAuditEventType(string $eventType): self
    {
        return new self(
            sprintf('El tipo de evento de auditoría "%s" no está registrado en el esquema de la carga útil.', $eventType),
            'unknown_audit_event_type',
        );
    }

    /**
     * Carga útil de auditoría que no respeta el esquema del tipo de evento.
     *
     * @param  list<string>  $missingKeys
     */
    public static function invalidAuditPayload(string $eventType, array $missingKeys): self
    {
        return new self(
            sprintf(
                'La carga útil del evento de auditoría "%s" no cumple el esquema: faltan las claves "%s".',
                $eventType,
                implode('", "', $missingKeys),
            ),
            'invalid_audit_payload',
        );
    }

    /**
     * Rol de usuario que no está registrado en el catálogo de roles.
     */
    public static function unknownUserRole(string $role): self
    {
        return new self(
            sprintf('El rol "%s" no es un rol válido de la aplicación.', $role),
            'unknown_user_role',
        );
    }

    /**
     * Formato de exportación de reporte no soportado.
     */
    public static function unknownReportFormat(string $format): self
    {
        return new self(
            sprintf('El formato de reporte "%s" no está soportado.', $format),
            'unknown_report_format',
        );
    }
}
