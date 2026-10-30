<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

use App\Domain\Shared\Exception\DomainException;
use Stringable;

/**
 * Rol de un usuario de la aplicación.
 *
 * Los literales son los del contrato de negocio y se persisten tal cual en
 * `APP_USER.role`. El rol de auditoría es el único facultado para consultar
 * el historial completo e inmutable de operaciones (TC020).
 */
final class UserRole implements Stringable
{
    public const OPERATOR = 'Operador de Producción';

    public const QUALITY_CONTROL = 'Técnico de Control de Calidad';

    public const LOGISTICS_COORDINATOR = 'Coordinador de Logística';

    public const PLANT_SUPERVISOR = 'Supervisor de Planta';

    public const AUDITOR_IT = 'Auditor / Administrador IT';

    private function __construct(private readonly string $value) {}

    /**
     * Construye el rol a partir de su literal.
     *
     * @throws DomainException Si el literal no corresponde a un rol válido.
     */
    public static function from(string $value): self
    {
        return match ($value) {
            self::OPERATOR,
            self::QUALITY_CONTROL,
            self::LOGISTICS_COORDINATOR,
            self::PLANT_SUPERVISOR,
            self::AUDITOR_IT => new self($value),
            default => throw DomainException::unknownUserRole($value),
        };
    }

    /**
     * Construye el rol a partir de su literal o devuelve null si es inválido.
     */
    public static function tryFrom(string $value): ?self
    {
        return match ($value) {
            self::OPERATOR,
            self::QUALITY_CONTROL,
            self::LOGISTICS_COORDINATOR,
            self::PLANT_SUPERVISOR,
            self::AUDITOR_IT => new self($value),
            default => null,
        };
    }

    /**
     * Literales de todos los roles de la aplicación.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::OPERATOR,
            self::QUALITY_CONTROL,
            self::LOGISTICS_COORDINATOR,
            self::PLANT_SUPERVISOR,
            self::AUDITOR_IT,
        ];
    }

    /**
     * Literal del rol.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Indica si el rol tiene acceso al historial completo de auditoría.
     */
    public function isAuditor(): bool
    {
        return $this->value === self::AUDITOR_IT;
    }

    /**
     * Indica si dos roles son el mismo.
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
