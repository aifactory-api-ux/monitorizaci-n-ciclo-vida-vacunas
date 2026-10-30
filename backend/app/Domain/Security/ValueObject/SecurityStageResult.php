<?php

declare(strict_types=1);

namespace App\Domain\Security\ValueObject;

/**
 * Resultado de una etapa de análisis de seguridad.
 *
 * Cada etapa del pipeline de entrega declara si ha detectado
 * vulnerabilidades y cuáles ha encontrado. La etapa que falla bloquea la
 * promoción del código (véase D20 y NFR011).
 */
final class SecurityStageResult
{
    /**
     * @param  list<string>  $findings
     */
    private function __construct(
        private readonly string $stage,
        private readonly bool $passed,
        private readonly string $summary,
        private readonly array $findings,
    ) {}

    /**
     * Etapa que ha terminado sin detectar vulnerabilidades.
     *
     * @param  list<string>  $findings
     */
    public static function passed(string $stage, string $summary = '', array $findings = []): self
    {
        return new self($stage, true, $summary, $findings);
    }

    /**
     * Etapa que ha detectado vulnerabilidades.
     *
     * @param  list<string>  $findings
     */
    public static function failed(string $stage, string $summary, array $findings = []): self
    {
        return new self($stage, false, $summary, $findings);
    }

    /**
     * Nombre de la etapa.
     */
    public function stage(): string
    {
        return $this->stage;
    }

    /**
     * Indica si la etapa ha terminado sin detectar vulnerabilidades.
     */
    public function hasPassed(): bool
    {
        return $this->passed;
    }

    /**
     * Resumen del resultado de la etapa.
     */
    public function summary(): string
    {
        return $this->summary;
    }

    /**
     * Vulnerabilidades detectadas por la etapa.
     *
     * @return list<string>
     */
    public function findings(): array
    {
        return $this->findings;
    }

    /**
     * Representación serializable del resultado.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'stage' => $this->stage,
            'passed' => $this->passed,
            'summary' => $this->summary,
            'findings' => $this->findings,
        ];
    }
}
