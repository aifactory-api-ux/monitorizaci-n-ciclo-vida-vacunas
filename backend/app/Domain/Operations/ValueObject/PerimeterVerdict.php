<?php

declare(strict_types=1);

namespace App\Domain\Operations\ValueObject;

/**
 * Veredicto de la política del perímetro privado.
 *
 * Recoge las rutas que el despliegue deja exposed sin exigir credencial y
 * que, por tanto, incumplen la restricción de despliegue dentro del
 * perímetro de MSD (véase NFR012).
 */
final class PerimeterVerdict
{
    /**
     * @param  list<string>  $violations
     */
    public function __construct(private readonly array $violations) {}

    /**
     * Indica si el despliegue respeta el perímetro privado.
     */
    public function isCompliant(): bool
    {
        return $this->violations === [];
    }

    /**
     * Rutas que incumplen la política.
     *
     * @return list<string>
     */
    public function violations(): array
    {
        return $this->violations;
    }

    /**
     * Representación serializable del veredicto.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'compliant' => $this->isCompliant(),
            'violations' => $this->violations,
        ];
    }
}
