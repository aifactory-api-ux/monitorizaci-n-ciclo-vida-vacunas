<?php

declare(strict_types=1);

namespace App\Domain\VaccineBatch\Service;

use App\Domain\Lims\ValueObject\GatingDecision;
use App\Domain\Shared\Exception\DomainException;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;

/**
 * Política de gating microbiológico del lote.
 *
 * La fase logística del Campus 3 sólo se habilita con la aprobación
 * microbiológica recibida de LIMS Labware (véase D5). La política no conoce
 * la persistencia: recibe la decisión del evaluador y decide sobre el avance
 * de fase, de modo que la regla se pueda probar sin base de datos.
 */
final class GatingPolicy
{
    /**
     * Indica si la fase de destino exige aprobación microbiológica.
     */
    public function requiresApproval(BatchPhase $target): bool
    {
        return $target->equals(BatchPhase::from(BatchPhase::LOGISTICS));
    }

    /**
     * Indica si el lote puede avanzar a la fase de destino.
     */
    public function canAdvance(BatchPhase $from, BatchPhase $to, GatingDecision $decision): bool
    {
        if (! $this->requiresApproval($to)) {
            return true;
        }

        return $decision->isApproved();
    }

    /**
     * Exige la aprobación microbiológica para el avance a la fase de destino.
     *
     * @throws DomainException Si el gating no se concede.
     */
    public function assertCanAdvance(BatchPhase $from, BatchPhase $to, GatingDecision $decision): void
    {
        if ($this->canAdvance($from, $to, $decision)) {
            return;
        }

        throw DomainException::gatingNotApproved(
            $decision->reason() ?? 'falta la aprobación microbiológica de LIMS Labware.',
        );
    }
}
