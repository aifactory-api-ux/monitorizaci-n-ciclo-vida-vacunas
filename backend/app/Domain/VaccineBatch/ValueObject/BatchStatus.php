<?php

declare(strict_types=1);

namespace App\Domain\VaccineBatch\ValueObject;

use App\Domain\Shared\Exception\DomainException;
use Stringable;

/**
 * Estado del ciclo de vida de un lote de vacuna.
 *
 * El estado se materializa en `VACCINE_BATCH.status` y es la información que
 * consume el frontend para decidir si un lote está bloqueado por el gating
 * LIMS, sin que el cliente calcule reglas de negocio (véase D5).
 */
final class BatchStatus implements Stringable
{
    /**
     * El lote progresa normalmente por el ciclo de vida.
     */
    public const IN_PROGRESS = 'En curso';

    /**
     * El lote ha sido recibido físicamente en el Campus 3.
     */
    public const RECEIVED = 'Recibido';

    /**
     * El ciclo logístico del lote está cerrado.
     */
    public const CLOSED = 'Cerrado';

    /**
     * El lote no puede avanzar a la fase logística por falta de aprobación
     * microbiológica del LIMS.
     */
    public const BLOCKED_BY_GATING = 'Bloqueado por Gating LIMS';

    private function __construct(private readonly string $value) {}

    /**
     * Construye el estado a partir de su literal.
     *
     * @throws DomainException Si el literal no corresponde a un estado válido.
     */
    public static function from(string $value): self
    {
        return match ($value) {
            self::IN_PROGRESS, self::RECEIVED, self::CLOSED, self::BLOCKED_BY_GATING => new self($value),
            default => throw DomainException::unknownStatus($value),
        };
    }

    /**
     * Construye el estado a partir de su literal o devuelve null si es inválido.
     */
    public static function tryFrom(string $value): ?self
    {
        return match ($value) {
            self::IN_PROGRESS, self::RECEIVED, self::CLOSED, self::BLOCKED_BY_GATING => new self($value),
            default => null,
        };
    }

    /**
     * Literales de todos los estados del lote.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return [self::IN_PROGRESS, self::RECEIVED, self::CLOSED, self::BLOCKED_BY_GATING];
    }

    /**
     * Literal del estado.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Indica si dos estados son el mismo.
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
