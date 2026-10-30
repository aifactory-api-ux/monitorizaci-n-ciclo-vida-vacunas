<?php

declare(strict_types=1);

namespace App\Domain\Audit\Service;

use App\Domain\Shared\Exception\DomainException;

/**
 * Esquema de la carga útil de cada tipo de evento del log de auditoría.
 *
 * Cada tipo de evento declara las claves obligatorias de su carga útil. La
 * validación se aplica antes de persistir la entrada, de modo que el log
 * estructurado siempre sea interpretable de forma automatizable (D10).
 * El valor de una clave puede ser nulo siempre que la clave exista.
 */
final class AuditEventPayloadSchema
{
    /**
     * Claves obligatorias por tipo de evento.
     *
     * @var array<string, list<string>>
     */
    private const REQUIRED_KEYS = [
        AuditEventType::BATCH_CREATED => [
            'phase',
            'status',
            'current_campus_id',
            'reference_id',
        ],
        AuditEventType::BATCH_PHASE_ADVANCED => [
            'from_phase',
            'to_phase',
            'current_campus_id',
            'transition_id',
        ],
        AuditEventType::BATCH_GATING_BLOCKED => [
            'to_phase',
            'reason',
            'status',
        ],
        AuditEventType::BATCH_PHYSICAL_RECEPTION_REGISTERED => [
            'phase',
            'status',
            'current_campus_id',
        ],
        AuditEventType::BATCH_LOGISTICS_CYCLE_CLOSED => [
            'phase',
            'status',
            'current_campus_id',
        ],
        AuditEventType::LIMS_IMPORT_REJECTED => [
            'error_code',
            'error_detail',
            'batch_reference',
            'reference_id',
        ],
        AuditEventType::LIMS_RESULT_IMPORTED => [
            'lims_result_id',
            'test_type',
            'result',
            'approved_by',
            'result_date',
            'source',
            'updated_at',
        ],
        AuditEventType::LIMS_RESULT_REGISTERED => [
            'lims_result_id',
            'test_type',
            'result',
            'approved_by',
            'result_date',
            'source',
            'updated_at',
        ],
        AuditEventType::LIMS_GATING_RELEASE_DENIED => [
            'gating_approved',
            'reason',
        ],
        AuditEventType::ALERT_RAISED => [
            'alert_id',
            'alert_type',
            'severity',
            'message',
            'detected_at',
        ],
        AuditEventType::API_WRITE_REQUESTED => [
            'method',
            'path',
            'status',
        ],
    ];

    /**
     * Comprueba que la carga útil respeta el esquema del tipo de evento.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws DomainException Si el tipo de evento es desconocido o falta alguna clave obligatoria.
     */
    public static function assertValid(string $eventType, array $payload): void
    {
        $requiredKeys = self::requiredKeysFor($eventType);

        $missingKeys = array_values(array_filter(
            $requiredKeys,
            static fn (string $key): bool => ! array_key_exists($key, $payload),
        ));

        if ($missingKeys !== []) {
            throw DomainException::invalidAuditPayload(
                $eventType,
                $missingKeys,
            );
        }
    }

    /**
     * Claves obligatorias del tipo de evento indicado.
     *
     * @return list<string>
     *
     * @throws DomainException Si el tipo de evento es desconocido.
     */
    public static function requiredKeysFor(string $eventType): array
    {
        if (! array_key_exists($eventType, self::REQUIRED_KEYS)) {
            throw DomainException::unknownAuditEventType($eventType);
        }

        return self::REQUIRED_KEYS[$eventType];
    }

    /**
     * Indica si el tipo de evento está reconocido por el esquema.
     */
    public static function supports(string $eventType): bool
    {
        return array_key_exists($eventType, self::REQUIRED_KEYS);
    }
}
