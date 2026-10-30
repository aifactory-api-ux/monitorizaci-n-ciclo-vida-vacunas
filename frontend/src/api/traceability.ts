import { accessToken } from '@/auth/authStore';
import { apiRequest } from '@/api/httpClient';
import type { Traceability } from '@/types/traceability';

export interface TraceabilityRequestOptions {
    signal?: AbortSignal;
}

/**
 * Obtiene la trazabilidad completa de un lote entre campus.
 */
export async function fetchBatchTraceability(
    batchId: string,
    options: TraceabilityRequestOptions = {},
): Promise<Traceability> {
    return apiRequest<Traceability>(`/batches/${batchId}/traceability`, {
        token: accessToken(),
        signal: options.signal,
    });
}
