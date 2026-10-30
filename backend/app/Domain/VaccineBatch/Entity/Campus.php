<?php

declare(strict_types=1);

namespace App\Domain\VaccineBatch\Entity;

/**
 * Campus de MSD que aloja un lote en una fase concreta.
 */
final class Campus
{
    /**
     * Campus de cultivo.
     */
    public const CULTURE = 'Campus 1';

    /**
     * Campus de formulación Sphereon.
     */
    public const SPHEREON = 'Campus 2';

    /**
     * Campus logístico.
     */
    public const LOGISTICS = 'Campus 3';

    private function __construct(
        private readonly string $id,
        private readonly string $name,
    ) {}

    /**
     * Construye el campus con su identificador y su nombre.
     */
    public static function of(string $id, string $name): self
    {
        return new self($id, $name);
    }

    /**
     * Identificador UUID del campus.
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Nombre del campus.
     */
    public function name(): string
    {
        return $this->name;
    }
}
