import { useQuery } from '@tanstack/react-query';

import { fetchBatchDetail } from '@/api/batches';

/**
 * Clave raíz de las consultas del detalle de un lote.
 */
export const BATCH_DETAIL_QUERY_KEY = 'batch';

/**
 * Consulta el detalle de un lote con sus resultados de LIMS y su gating.
 */
export function useBatchDetail(batchId: string | null) {
    return useQuery({
        queryKey: [BATCH_DETAIL_QUERY_KEY, batchId],
        queryFn: ({ signal }) => fetchBatchDetail(batchId as string, { signal }),
        enabled: batchId !== null,
    });
}
