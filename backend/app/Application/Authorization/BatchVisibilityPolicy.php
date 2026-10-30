<?php

declare(strict_types=1);

namespace App\Application\Authorization;

use App\Domain\User\ValueObject\UserRole;
use App\Domain\VaccineBatch\Entity\VaccineBatch;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;

/**
 * Alcance de la visibilidad de los lotes por rol.
 *
 * El Operador de Producción sólo visualiza los lotes que están en la fase de
 * cultivo, que son los que intervienen en su puesto de trabajo. Para el resto
 * de roles la visibilidad es completa (véase D9 y TC027).
 */
final class BatchVisibilityPolicy
{
    /**
     * Fases visibles por cada rol con visibilidad acotada.
     *
     * @var array<string, list<string>>
     */
    private const VISIBLE_PHASES = [
        UserRole::OPERATOR => [
            BatchPhase::CULTURE,
        ],
    ];

    /**
     * Fases que el rol puede visualizar.
     *
     * @return list<string>
     */
    public function visiblePhases(UserRole $role): array
    {
        return self::VISIBLE_PHASES[$role->value()] ?? BatchPhase::values();
    }

    /**
     * Indica si el rol puede visualizar un lote en la fase indicada.
     */
    public function allows(UserRole $role, BatchPhase $phase): bool
    {
        return in_array($phase->value(), $this->visiblePhases($role), true);
    }

    /**
     * Lotes que el rol puede visualizar, en el orden recibido.
     *
     * @param  list<VaccineBatch>  $batches
     * @return list<VaccineBatch>
     */
    public function filter(UserRole $role, array $batches): array
    {
        return array_values(array_filter(
            $batches,
            fn (VaccineBatch $batch): bool => $this->allows($role, $batch->phase()),
        ));
    }
}
