<?php

declare(strict_types=1);

namespace App\Domain\Security\ValueObject;

/**
 * Veredicto de la etapa de seguridad de la promoción.
 *
 * La promoción sólo se permite cuando todas las etapas de análisis han
 * terminado sin detectar vulnerabilidades. Una etapa que no se ha ejecutado
 * bloquea la promoción: el despliegue nunca se realiza sin análisis
 * (véase D20 y NFR011).
 */
final class SecurityGateVerdict
{
    /**
     * @param  list<SecurityStageResult>  $results
     */
    public function __construct(private readonly array $results) {}

    /**
     * Resultados de las etapas evaluadas.
     *
     * @return list<SecurityStageResult>
     */
    public function results(): array
    {
        return $this->results;
    }

    /**
     * Indica si la promoción del código está permitida.
     */
    public function allowsPromotion(): bool
    {
        return $this->results !== [] && $this->blockedStages() === [];
    }

    /**
     * Etapas que bloquean la promoción.
     *
     * @return list<string>
     */
    public function blockedStages(): array
    {
        $blocked = [];

        foreach ($this->results as $result) {
            if (! $result->hasPassed()) {
                $blocked[] = $result->stage();
            }
        }

        return $blocked;
    }

    /**
     * Representación serializable del veredicto.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'allows_promotion' => $this->allowsPromotion(),
            'blocked_stages' => $this->blockedStages(),
            'stages' => array_map(
                static fn (SecurityStageResult $result): array => $result->toArray(),
                $this->results,
            ),
        ];
    }
}
