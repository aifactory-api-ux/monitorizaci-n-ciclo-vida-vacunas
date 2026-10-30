<?php

declare(strict_types=1);

namespace App\Domain\Lims\ValueObject;

use App\Domain\Lims\Entity\LimsResult;

/**
 * Decisión del gating microbiológico de un lote.
 *
 * La decisión viaja desde el dominio hasta la API para que el bloqueo se
 * materialice en el `status` del lote y el frontend no tenga que calcular la
 * regla (véase D5).
 */
final class GatingDecision
{
    private function __construct(
        private readonly bool $approved,
        private readonly ?string $reason,
        private readonly ?LimsResult $approvedResult,
    ) {}

    /**
     * Gating concedido a partir del resultado microbiológico aprobado.
     */
    public static function approved(LimsResult $result): self
    {
        return new self(true, null, $result);
    }

    /**
     * Gating denegado con el motivo de la denegación.
     */
    public static function rejected(string $reason): self
    {
        return new self(false, $reason, null);
    }

    /**
     * Indica si el gating se concede.
     */
    public function isApproved(): bool
    {
        return $this->approved;
    }

    /**
     * Motivo de la denegación, o null si el gating se concede.
     */
    public function reason(): ?string
    {
        return $this->reason;
    }

    /**
     * Resultado microbiológico que concede el gating, si lo hay.
     */
    public function approvedResult(): ?LimsResult
    {
        return $this->approvedResult;
    }
}
