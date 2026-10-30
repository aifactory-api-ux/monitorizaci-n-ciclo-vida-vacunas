<?php

declare(strict_types=1);

namespace App\Domain\Operations\ValueObject;

/**
 * Exposición de una ruta del despliegue.
 *
 * Describe una ruta que el despliegue publica y si exige un token Bearer para
 * atender las peticiones. La política del perímetro privado sólo admite
 * exposures públicas para el sondeo de disponibilidad y la emisión del token
 * (véase NFR012).
 */
final class PerimeterExposure
{
    private function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly bool $requiresAuthentication,
    ) {}

    /**
     * Construye la exposición de una ruta.
     */
    public static function expose(string $method, string $uri, bool $requiresAuthentication): self
    {
        return new self(strtoupper($method), $uri, $requiresAuthentication);
    }

    /**
     * Método HTTP de la ruta.
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * URI de la ruta.
     */
    public function uri(): string
    {
        return $this->uri;
    }

    /**
     * Indica si la ruta exige un token Bearer válido.
     */
    public function requiresAuthentication(): bool
    {
        return $this->requiresAuthentication;
    }

    /**
     * Indica si la ruta atiende peticiones sin credencial.
     */
    public function isPublic(): bool
    {
        return ! $this->requiresAuthentication;
    }

    /**
     * Identificador legible de la exposición.
     */
    public function describe(): string
    {
        return sprintf('%s %s', $this->method, $this->uri);
    }
}
