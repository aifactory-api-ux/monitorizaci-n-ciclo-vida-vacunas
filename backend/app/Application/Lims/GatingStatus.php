<?php

declare(strict_types=1);

namespace App\Application\Lims;

use App\Domain\Lims\Entity\LimsResult;
use App\Domain\Lims\ValueObject\GatingDecision;

/**
 * Estado del gating microbiológico de un lote.
 *
 * Transporta la decisión del dominio hasta la API para que el bloqueo se
 * exponga como dato y no se calcule en el cliente (véase D5).
 */
final class GatingStatus
{
    public function __construct(
        private readonly string $batchId,
        private readonly GatingDecision $decision,
    ) {}

    /**
     * Identificador UUID del lote evaluado.
     */
    public function batchId(): string
    {
        return $this->batchId;
    }

    /**
     * Decisión del gating.
     */
    public function decision(): GatingDecision
    {
        return $this->decision;
    }

    /**
     * Indica si el gating se concede.
     */
    public function isApproved(): bool
    {
        return $this->decision->isApproved();
    }

    /**
     * Motivo de la denegación, o null si el gating se concede.
     */
    public function reason(): ?string
    {
        return $this->decision->reason();
    }

    /**
     * Resultado microbiológico que concede el gating, si lo hay.
     */
    public function approvedResult(): ?LimsResult
    {
        return $this->decision->approvedResult();
    }
}
