<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Domain\Search\Port\BatchSearchRepository;
use App\Domain\Search\ValueObject\BatchSearchCriteria;
use App\Domain\Search\ValueObject\BatchSearchResult;

/**
 * Doble que contabiliza las consultas de búsqueda de lotes.
 *
 * Envuelve al repositorio de búsqueda real para comprobar cuántas veces
 * llega la consulta a la base de datos: un resultado servido desde la caché
 * no debe volver a consultar el repositorio.
 */
final class CountingBatchSearchRepository implements BatchSearchRepository
{
    /**
     * Número de consultas recibidas.
     */
    private int $calls = 0;

    public function __construct(
        private readonly BatchSearchRepository $inner,
    ) {}

    public function search(BatchSearchCriteria $criteria): BatchSearchResult
    {
        $this->calls++;

        return $this->inner->search($criteria);
    }

    /**
     * Número de consultas recibidas.
     */
    public function calls(): int
    {
        return $this->calls;
    }
}
