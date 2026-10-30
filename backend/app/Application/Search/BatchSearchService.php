<?php

declare(strict_types=1);

namespace App\Application\Search;

use App\Application\Batches\ListBatches;
use App\Domain\Audit\Port\OperationDurationLogger;
use App\Domain\Search\Port\BatchSearchCache;
use App\Domain\Search\ValueObject\BatchSearchCriteria;
use App\Domain\Search\ValueObject\BatchSearchResult;

/**
 * Caso de uso de búsqueda avanzada de lotes.
 *
 * Cachea la página de resultados por el hash de los filtros y registra la
 * duración de la consulta, de modo que la latencia de la búsqueda sea
 * medible desde el log estructurado (véase D14 y D21).
 */
final class BatchSearchService
{
    /**
     * Operación registrada en el log de duraciones.
     */
    public const OPERATION = 'search.batches';

    public function __construct(
        private readonly ListBatches $listBatches,
        private readonly BatchSearchCache $cache,
        private readonly OperationDurationLogger $durationLogger,
        private readonly int $cacheTtlSeconds,
    ) {}

    /**
     * Devuelve la página de lotes que satisfacen los criterios indicados.
     */
    public function execute(BatchSearchCriteria $criteria): BatchSearchResult
    {
        $startedAt = microtime(true);

        $result = $this->cache->remember(
            $criteria->cacheKey(),
            fn (): BatchSearchResult => $this->listBatches->search($criteria),
            $this->cacheTtlSeconds,
        );

        $this->durationLogger->record(
            self::OPERATION,
            (microtime(true) - $startedAt) * 1000,
            [
                'page' => $criteria->page(),
                'per_page' => $criteria->perPage(),
                'total' => $result->total(),
                'returned' => count($result->items()),
            ],
        );

        return $result;
    }
}
