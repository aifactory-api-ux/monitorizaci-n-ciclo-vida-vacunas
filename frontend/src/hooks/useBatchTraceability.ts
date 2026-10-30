import { useQuery } from '@tanstack/react-query';

import { fetchBatchTraceability } from '@/api/traceability';

/**
 * Clave raíz de las consultas de trazabilidad.
 *
 * Se anida bajo la clave de lote para que la invalidación por evento de
 * tiempo real alcance también la trazabilidad (véase D12).
 */
export const BATCH_TRACEABILITY_QUERY_KEY = 'batch-traceability';

/**
 * Consulta la trazabilidad completa de un lote.
 */
export function useBatchTraceability(batchId: string | null) {
    return useQuery({
        queryKey: ['batch', batchId, BATCH_TRACEABILITY_QUERY_KEY],
        queryFn: ({ signal }) => fetchBatchTraceability(batchId as string, { signal }),
        enabled: batchId !== null && batchId !== '',
    });
}
