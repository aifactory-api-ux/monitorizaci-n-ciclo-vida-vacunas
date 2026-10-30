<?php

declare(strict_types=1);

namespace App\Domain\Search\ValueObject;

use App\Domain\Search\Entity\BatchSearchItem;

/**
 * Página de resultados de la búsqueda avanzada de lotes.
 *
 * Además de los lotes de la página, conserva el total de coincidencias del
 * filtro, que la API devuelve junto con la página solicitada.
 */
final class BatchSearchResult
{
    /**
     * @param  list<BatchSearchItem>  $items
     */
    public function __construct(
        private readonly array $items,
        private readonly int $total,
        private readonly int $page,
        private readonly int $perPage,
    ) {}

    /**
     * Lotes de la página.
     *
     * @return list<BatchSearchItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * Total de lotes que satisfacen el filtro.
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * Número de página devuelta.
     */
    public function page(): int
    {
        return $this->page;
    }

    /**
     * Número de resultados por página de la consulta.
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * Número de páginas que cubre el total de coincidencias.
     */
    public function lastPage(): int
    {
        if ($this->perPage < 1) {
            return 1;
        }

        return max(1, (int) ceil($this->total / $this->perPage));
    }

    /**
     * Indica si el filtro no admite ningún lote.
     */
    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}
