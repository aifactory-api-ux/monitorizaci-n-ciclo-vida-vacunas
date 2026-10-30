<?php

declare(strict_types=1);

namespace App\Domain\Lims\ValueObject;

/**
 * Resultado de validar la consistencia de una carga de LIMS Labware.
 *
 * Una carga válida no tiene código de error; una carga inconsistente expone
 * el código estable del incumplimiento y el detalle que se registra en el
 * log estructurado (véase D8).
 */
final class LimsPayloadValidation
{
    private function __construct(
        private readonly bool $valid,
        private readonly ?string $errorCode,
        private readonly ?string $errorDetail,
    ) {}

    /**
     * Carga consistente.
     */
    public static function accepted(): self
    {
        return new self(true, null, null);
    }

    /**
     * Carga inconsistente.
     */
    public static function rejected(string $errorCode, string $errorDetail): self
    {
        return new self(false, $errorCode, $errorDetail);
    }

    /**
     * Indica si la carga es consistente.
     */
    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * Código estable del incumplimiento, o null si la carga es válida.
     */
    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Detalle del incumplimiento, o null si la carga es válida.
     */
    public function errorDetail(): ?string
    {
        return $this->errorDetail;
    }
}
