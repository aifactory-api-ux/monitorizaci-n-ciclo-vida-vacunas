<?php

declare(strict_types=1);

namespace App\Application\Authorization;

use App\Domain\Shared\Exception\UnauthorizedOperationException;
use App\Domain\User\ValueObject\UserRole;

/**
 * Matriz de permisos por rol de la aplicación.
 *
 * Es la única fuente de verdad de qué operación puede ejecutar cada rol
 * (véase D9). La capa Api la aplica mediante el middleware `EnsureUserRole`
 * y los casos de uso la consultan antes de alterar datos.
 */
final class OperationPolicy
{
    /**
     * Alta de un lote de vacuna.
     */
    public const BATCH_CREATE = 'batch.create';

    /**
     * Avance secuencial de fase del lote.
     */
    public const BATCH_ADVANCE_PHASE = 'batch.advance_phase';

    /**
     * Registro de la recepción física del lote en el Campus 3.
     */
    public const BATCH_REGISTER_PHYSICAL_RECEPTION = 'batch.register_physical_reception';

    /**
     * Cierre del ciclo logístico del lote.
     */
    public const BATCH_CLOSE_LOGISTICS_CYCLE = 'batch.close_logistics_cycle';

    /**
     * Importación de resultados desde LIMS Labware.
     */
    public const LIMS_IMPORT = 'lims.import';

    /**
     * Registro de un resultado microbiológico del lote.
     */
    public const LIMS_REGISTER_RESULT = 'lims.register_result';

    /**
     * Liberación del lote con el gating microbiológico aprobado.
     */
    public const GATING_RELEASE = 'gating.release';

    /**
     * Consulta del historial completo e inmutable de auditoría.
     */
    public const AUDIT_READ = 'audit.read';

    /**
     * Operaciones admitidas por cada rol.
     *
     * @var array<string, list<string>>
     */
    private const MATRIX = [
        UserRole::OPERATOR => [
            self::BATCH_CREATE,
            self::BATCH_ADVANCE_PHASE,
            self::BATCH_REGISTER_PHYSICAL_RECEPTION,
            self::BATCH_CLOSE_LOGISTICS_CYCLE,
        ],
        UserRole::QUALITY_CONTROL => [
            self::LIMS_IMPORT,
            self::LIMS_REGISTER_RESULT,
            self::GATING_RELEASE,
        ],
        UserRole::LOGISTICS_COORDINATOR => [
            self::BATCH_REGISTER_PHYSICAL_RECEPTION,
            self::BATCH_CLOSE_LOGISTICS_CYCLE,
        ],
        UserRole::PLANT_SUPERVISOR => [
            self::BATCH_CREATE,
            self::BATCH_ADVANCE_PHASE,
            self::BATCH_REGISTER_PHYSICAL_RECEPTION,
            self::BATCH_CLOSE_LOGISTICS_CYCLE,
            self::LIMS_IMPORT,
            self::LIMS_REGISTER_RESULT,
            self::GATING_RELEASE,
        ],
        UserRole::AUDITOR_IT => [
            self::AUDIT_READ,
        ],
    ];

    /**
     * Todas las operaciones del contrato.
     *
     * @return list<string>
     */
    public static function operations(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::MATRIX))));
    }

    /**
     * Indica si el rol puede ejecutar la operación.
     */
    public function allows(UserRole $role, string $operation): bool
    {
        return in_array($operation, self::MATRIX[$role->value()] ?? [], true);
    }

    /**
     * Operaciones admitidas por el rol.
     *
     * @return list<string>
     */
    public function operationsFor(UserRole $role): array
    {
        return self::MATRIX[$role->value()] ?? [];
    }

    /**
     * Exige que el rol pueda ejecutar la operación.
     *
     * @throws UnauthorizedOperationException Si la operación excede el perfil del rol.
     */
    public function assertAllowed(UserRole $role, string $operation): void
    {
        if (! $this->allows($role, $operation)) {
            throw UnauthorizedOperationException::operationForbidden($role->value(), $operation);
        }
    }
}
