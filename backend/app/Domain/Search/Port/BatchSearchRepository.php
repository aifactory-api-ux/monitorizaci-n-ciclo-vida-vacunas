<?php

declare(strict_types=1);

namespace App\Domain\Search\Port;

use App\Domain\Search\ValueObject\BatchSearchCriteria;
use App\Domain\Search\ValueObject\BatchSearchResult;

/**
 * Puerto de consulta de la búsqueda avanzada de lotes.
 *
 * La implementación resuelve los filtros sobre el modelo relacional y
 * devuelve la página de resultados con el total de coincidencias (véase D14).
 */
interface BatchSearchRepository
{
    /**
     * Devuelve la página de lotes que satisfacen los criterios indicados.
     */
    public function search(BatchSearchCriteria $criteria): BatchSearchResult;
}
