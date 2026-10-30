import { useQuery } from '@tanstack/react-query';

import { fetchBatches, type BatchFilters } from '@/api/batches';

/**
 * Clave raíz de las consultas del listado de lotes.
 *
 * La invalidación por evento de tiempo real usa esta clave, de forma que
 * cualquier consulta del listado se queda obsoleta cuando el backend difunde
 * un cambio (véase D12).
 */
export const BATCHES_QUERY_KEY = 'batches';

/**
 * Consulta la página de lotes con los filtros indicados.
 */
export function useBatches(filters: BatchFilters = {}) {
    return useQuery({
        queryKey: [BATCHES_QUERY_KEY, filters],
        queryFn: ({ signal }) => fetchBatches(filters, { signal }),
    });
}
