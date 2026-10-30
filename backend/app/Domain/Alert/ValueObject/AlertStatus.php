<?php

declare(strict_types=1);

namespace App\Domain\Alert\ValueObject;

use App\Domain\Shared\Exception\DomainException;
use Stringable;

/**
 * Estado de alerta de un lote.
 *
 * La tabla `ALERT` no tiene columna de estado: el estado de la alerta se
 * representa con la severidad persistida (véase D6). El estado de alerta de
 * un lote distingue, por tanto, si el lote tiene alguna alerta de prioridad
 * máxima registrada, y es el criterio de filtrado del listado de lotes.
 */
final class AlertStatus implements Stringable
{
    /**
     * El lote tiene al menos una alerta de prioridad máxima registrada.
     */
    public const WITH_CRITICAL_ALERT = 'Con alerta crítica';

    /**
     * El lote no tiene ninguna alerta de prioridad máxima registrada.
     */
    public const WITHOUT_CRITICAL_ALERT = 'Sin alerta crítica';

    private function __construct(private readonly string $value) {}

    /**
     * Construye el estado de alerta a partir de su literal.
     *
     * @throws DomainException Si el literal no corresponde a un estado válido.
     */
    public static function from(string $value): self
    {
        return match ($value) {
            self::WITH_CRITICAL_ALERT, self::WITHOUT_CRITICAL_ALERT => new self($value),
            default => throw DomainException::unknownAlertStatus($value),
        };
    }

    /**
     * Construye el estado de alerta a partir de su literal o devuelve null si
     * es inválido.
     */
    public static function tryFrom(string $value): ?self
    {
        return match ($value) {
            self::WITH_CRITICAL_ALERT, self::WITHOUT_CRITICAL_ALERT => new self($value),
            default => null,
        };
    }

    /**
     * Literales de todos los estados de alerta de un lote.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return [self::WITH_CRITICAL_ALERT, self::WITHOUT_CRITICAL_ALERT];
    }

    /**
     * Literal del estado de alerta.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Indica si el estado exige la presencia de una alerta crítica.
     */
    public function requiresCriticalAlert(): bool
    {
        return $this->value === self::WITH_CRITICAL_ALERT;
    }

    /**
     * Indica si dos estados de alerta son el mismo.
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
