<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Domain\Search\Port\BatchSearchCache;
use App\Domain\Search\ValueObject\BatchSearchResult;
use Closure;
use Illuminate\Contracts\Cache\Repository;

/**
 * Adaptador de la caché de resultados de búsqueda sobre el almacén de caché
 * de la aplicación.
 *
 * En el despliegue el almacén es Redis (véase `config/search.php`). Cada
 * resultado se identifica por el hash de sus filtros y por una generación
 * que se incrementa cuando cambia el estado de un lote: la invalidación es
 * constante y no requiere recorrer las claves almacenadas (véase D14).
 */
final class RedisBatchSearchCache implements BatchSearchCache
{
    /**
     * Clave del contador de generación.
     */
    private const GENERATION_KEY = 'search:generation';

    public function __construct(
        private readonly Repository $store,
        private readonly string $prefix,
    ) {}

    public function remember(string $key, Closure $resolver, int $ttlSeconds): BatchSearchResult
    {
        $cacheKey = $this->cacheKey($key);

        $cached = $this->store->get($cacheKey);

        if ($cached instanceof BatchSearchResult) {
            return $cached;
        }

        $result = $resolver();

        $this->store->put($cacheKey, $result, $ttlSeconds);

        return $result;
    }

    public function invalidate(): void
    {
        $this->store->increment(self::GENERATION_KEY);
    }

    /**
     * Construye la clave de la caché con la generación vigente.
     */
    private function cacheKey(string $key): string
    {
        return sprintf('%s:g%d:%s', $this->prefix, $this->generation(), $key);
    }

    /**
     * Generación vigente de la caché.
     */
    private function generation(): int
    {
        $generation = $this->store->get(self::GENERATION_KEY);

        return is_numeric($generation) ? (int) $generation : 0;
    }
}
