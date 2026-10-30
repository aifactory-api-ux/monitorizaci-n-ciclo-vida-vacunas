<?php

declare(strict_types=1);

namespace App\Domain\Alert\ValueObject;

use App\Domain\Shared\Exception\DomainException;
use Stringable;

/**
 * Severidad de la alerta operativa.
 *
 * La severidad persistida representa también el estado de la alerta: la tabla
 * `ALERT` no tiene columna de estado (véase D6). La severidad `Crítica` es la
 * prioridad máxima y es la única que se notifica a los canales de la
 * organización.
 */
final class AlertSeverity implements Stringable
{
    /**
     * Prioridad máxima: la alerta exige la atención inmediata del Supervisor de
     * Planta.
     */
    public const CRITICAL = 'Crítica';

    /**
     * Prioridad no máxima: la alerta se registra y se consulta en el histórico,
     * pero no dispara notificación a los canales de la organización.
     */
    public const WARNING = 'Advertencia';

    private function __construct(private readonly string $value) {}

    /**
     * Construye la severidad a partir de su literal.
     *
     * @throws DomainException Si el literal no corresponde a una severidad válida.
     */
    public static function from(string $value): self
    {
        return match ($value) {
            self::CRITICAL, self::WARNING => new self($value),
            default => throw DomainException::unknownAlertSeverity($value),
        };
    }

    /**
     * Construye la severidad a partir de su literal o devuelve null si es
     * inválida.
     */
    public static function tryFrom(string $value): ?self
    {
        return match ($value) {
            self::CRITICAL, self::WARNING => new self($value),
            default => null,
        };
    }

    /**
     * Literales de todas las severidades.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return [self::CRITICAL, self::WARNING];
    }

    /**
     * Literal de la severidad.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Indica si la severidad es la de prioridad máxima.
     */
    public function isCritical(): bool
    {
        return $this->value === self::CRITICAL;
    }

    /**
     * Indica si dos severidades son la misma.
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
