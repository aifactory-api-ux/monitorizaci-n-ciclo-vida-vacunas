<?php

declare(strict_types=1);

namespace App\Domain\VaccineBatch\ValueObject;

use App\Domain\Shared\Exception\DomainException;
use Stringable;

/**
 * Fase del ciclo de vida de un lote de vacuna.
 *
 * Los literales son los del contrato de negocio y se persisten tal cual en
 * `VACCINE_BATCH.phase` y en `PHASE_TRANSITION.from_phase` y `to_phase`.
 */
final class BatchPhase implements Stringable
{
    /**
     * Fase de cultivo en el Campus 1.
     */
    public const CULTURE = 'Fase de cultivo';

    /**
     * Fase de formulación Sphereon en el Campus 2.
     */
    public const SPHEREON = 'Formulación Sphereon (Campus 2)';

    /**
     * Fase logística en el Campus 3.
     */
    public const LOGISTICS = 'Fase logística (Campus 3)';

    private function __construct(private readonly string $value) {}

    /**
     * Construye la fase a partir de su literal.
     *
     * @throws DomainException Si el literal no corresponde a una fase válida.
     */
    public static function from(string $value): self
    {
        return match ($value) {
            self::CULTURE, self::SPHEREON, self::LOGISTICS => new self($value),
            default => throw DomainException::unknownPhase($value),
        };
    }

    /**
     * Construye la fase a partir de su literal o devuelve null si es inválida.
     */
    public static function tryFrom(string $value): ?self
    {
        return match ($value) {
            self::CULTURE, self::SPHEREON, self::LOGISTICS => new self($value),
            default => null,
        };
    }

    /**
     * Literales de todas las fases del ciclo de vida.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return [self::CULTURE, self::SPHEREON, self::LOGISTICS];
    }

    /**
     * Literal de la fase.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Indica si dos fases son la misma.
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
