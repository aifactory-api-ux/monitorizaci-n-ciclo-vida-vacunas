<?php

declare(strict_types=1);

namespace App\Domain\Audit\Service;

/**
 * Catálogo canónico de tipos de evento del log estructurado de auditoría.
 *
 * Centraliza los literales emitidos por los casos de uso para que el esquema
 * de la carga útil y los registros históricos compartan una única fuente de
 * verdad (véase D10 y TC026).
 */
final class AuditEventType
{
    public const BATCH_CREATED = 'batch.created';

    public const BATCH_PHASE_ADVANCED = 'batch.phase.advanced';

    public const BATCH_GATING_BLOCKED = 'batch.gating.blocked';

    public const BATCH_PHYSICAL_RECEPTION_REGISTERED = 'batch.physical_reception.registered';

    public const BATCH_LOGISTICS_CYCLE_CLOSED = 'batch.logistics_cycle.closed';

    public const LIMS_IMPORT_REJECTED = 'lims.import.rejected';

    public const LIMS_RESULT_IMPORTED = 'lims.result.imported';

    public const LIMS_RESULT_REGISTERED = 'lims.result.registered';

    public const LIMS_GATING_RELEASE_DENIED = 'lims.gating.release.denied';

    public const ALERT_RAISED = 'alert.raised';

    /**
     * Petición de escritura atendida por la API.
     */
    public const API_WRITE_REQUESTED = 'api.write.requested';

    /**
     * Todos los tipos de evento reconocidos por el dominio.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::BATCH_CREATED,
            self::BATCH_PHASE_ADVANCED,
            self::BATCH_GATING_BLOCKED,
            self::BATCH_PHYSICAL_RECEPTION_REGISTERED,
            self::BATCH_LOGISTICS_CYCLE_CLOSED,
            self::LIMS_IMPORT_REJECTED,
            self::LIMS_RESULT_IMPORTED,
            self::LIMS_RESULT_REGISTERED,
            self::LIMS_GATING_RELEASE_DENIED,
            self::ALERT_RAISED,
            self::API_WRITE_REQUESTED,
        ];
    }
}
