<?php

declare(strict_types=1);

namespace App\Domain\VaccineBatch\Entity;

/**
 * Referencia de vacuna del catálogo a la que apunta un lote.
 */
final class VaccineReference
{
    private function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $family,
    ) {}

    /**
     * Construye la referencia con sus datos de catálogo.
     */
    public static function of(string $id, string $name, string $family): self
    {
        return new self($id, $name, $family);
    }

    /**
     * Identificador UUID de la referencia.
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Nombre de la referencia.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Familia de producto a la que pertenece la referencia.
     */
    public function family(): string
    {
        return $this->family;
    }
}
