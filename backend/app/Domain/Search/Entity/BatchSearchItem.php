<?php

declare(strict_types=1);

namespace App\Domain\Search\Entity;

use App\Domain\VaccineBatch\Entity\VaccineBatch;

/**
 * Lote devuelto por la búsqueda avanzada.
 *
 * Es la proyección de lectura del lote con la información del catálogo de
 * referencias que permite distinguirlo del resto: la familia y el nombre del
 * producto se muestran en el listado (véase D14).
 */
final class BatchSearchItem
{
    public function __construct(
        private readonly VaccineBatch $batch,
        private readonly string $referenceName,
        private readonly string $referenceFamily,
    ) {}

    /**
     * Lote encontrado.
     */
    public function batch(): VaccineBatch
    {
        return $this->batch;
    }

    /**
     * Nombre del producto de referencia del lote.
     */
    public function referenceName(): string
    {
        return $this->referenceName;
    }

    /**
     * Familia de producto del lote.
     */
    public function referenceFamily(): string
    {
        return $this->referenceFamily;
    }
}
