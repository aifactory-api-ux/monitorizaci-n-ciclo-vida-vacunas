<?php

declare(strict_types=1);

namespace App\Domain\Alert\ValueObject;

use App\Domain\Shared\Exception\DomainException;
use Stringable;

/**
 * Tipo de la alerta operativa de prioridad máxima.
 *
 * Los literales son los del contrato de negocio y se persisten tal cual en
 * `ALERT.alert_type`.
 */
final class AlertType implements Stringable
{
    /**
     * Desviación térmica detectada sobre el lote.
     */
    public const THERMAL_DEVIATION = 'Desviación térmica';

    /**
     * Anomalía de formulación Sphereon detectada sobre el lote.
     */
    public const SPHEREON_ANOMALY = 'Anomalía Sphereon';

    private function __construct(private readonly string $value) {}

    /**
     * Construye el tipo de alerta a partir de su literal.
     *
     * @throws DomainException Si el literal no corresponde a un tipo válido.
     */
    public static function from(string $value): self
    {
        return match ($value) {
            self::THERMAL_DEVIATION, self::SPHEREON_ANOMALY => new self($value),
            default => throw DomainException::unknownAlertType($value),
        };
    }

    /**
     * Construye el tipo de alerta a partir de su literal o devuelve null si es
     * inválido.
     */
    public static function tryFrom(string $value): ?self
    {
        return match ($value) {
            self::THERMAL_DEVIATION, self::SPHEREON_ANOMALY => new self($value),
            default => null,
        };
    }

    /**
     * Literales de todos los tipos de alerta.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return [self::THERMAL_DEVIATION, self::SPHEREON_ANOMALY];
    }

    /**
     * Literal del tipo de alerta.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Indica si dos tipos de alerta son el mismo.
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
