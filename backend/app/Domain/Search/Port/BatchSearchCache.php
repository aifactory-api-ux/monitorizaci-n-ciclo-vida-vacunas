<?php

declare(strict_types=1);

namespace App\Domain\Search\Port;

use App\Domain\Search\ValueObject\BatchSearchResult;
use Closure;

/**
 * Puerto de la caché de resultados de búsqueda.
 *
 * La caché se indexa por el hash de los filtros y se invalida cuando cambia
 * el estado de un lote, de modo que el listado nunca muestra un resultado
 * anterior al cambio (véase D14).
 */
interface BatchSearchCache
{
    /**
     * Devuelve el resultado cacheado para la clave indicada o lo calcula y
     * almacena con el tiempo de vida dado.
     *
     * @param  Closure(): BatchSearchResult  $resolver
     */
    public function remember(string $key, Closure $resolver, int $ttlSeconds): BatchSearchResult;

    /**
     * Invalida los resultados cacheados.
     */
    public function invalidate(): void;
}
