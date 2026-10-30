import { accessToken } from '@/auth/authStore';
import { apiRequest } from '@/api/httpClient';
import type { Batch, BatchDetail, BatchPage } from '@/types/batch';
import type { PhaseTransition } from '@/types/traceability';

/**
 * Filtros del listado de lotes.
 *
 * Los nombres coinciden con los parámetros que acepta el endpoint de búsqueda
 * avanzada, de modo que el frontend no renombra ni reinterpreta los criterios.
 */
export interface BatchFilters {
    family?: string;
    phase?: string;
    alert_status?: string;
    date_from?: string;
    date_to?: string;
    page?: number;
    per_page?: number;
}

export interface BatchRequestOptions {
    signal?: AbortSignal;
}

/**
 * Obtiene una página de lotes con los filtros indicados.
 */
export async function fetchBatches(
    filters: BatchFilters = {},
    options: BatchRequestOptions = {},
): Promise<BatchPage> {
    return apiRequest<BatchPage>('/search/batches', {
        token: accessToken(),
        signal: options.signal,
        query: {
            family: filters.family,
            phase: filters.phase,
            alert_status: filters.alert_status,
            date_from: filters.date_from,
            date_to: filters.date_to,
            page: filters.page,
            per_page: filters.per_page,
        },
    });
}

/**
 * Obtiene el detalle de un lote con sus resultados de LIMS y su gating.
 */
export async function fetchBatchDetail(
    batchId: string,
    options: BatchRequestOptions = {},
): Promise<BatchDetail> {
    return apiRequest<BatchDetail>(`/batches/${batchId}`, {
        token: accessToken(),
        signal: options.signal,
    });
}

/**
 * Registra la recepción física del lote en el Campus 3.
 *
 * El backend exige que el lote esté en fase logística y responde con 422
 * cuando la operación no está permitida, de modo que el frontend no replica
 * esa regla.
 */
export async function registerPhysicalReception(
    batchId: string,
    options: BatchRequestOptions = {},
): Promise<Batch> {
    return apiRequest<Batch>(`/batches/${batchId}/reception`, {
        method: 'POST',
        token: accessToken(),
        signal: options.signal,
    });
}

/**
 * Cierra el ciclo logístico del lote.
 *
 * El backend exige que la recepción física esté registrada y responde con 422
 * cuando la operación no está permitida.
 */
export async function closeLogisticsCycle(
    batchId: string,
    options: BatchRequestOptions = {},
): Promise<Batch> {
    return apiRequest<Batch>(`/batches/${batchId}/logistics-close`, {
        method: 'POST',
        token: accessToken(),
        signal: options.signal,
    });
}

/**
 * Respuesta del avance de fase de un lote.
 */
export interface AdvancePhaseResponse {
    batch: Batch;
    transition: PhaseTransition;
}

/**
 * Solicita el avance de fase de un lote.
 *
 * El backend comprueba la secuencialidad de las fases y el gating
 * microbiológico, y responde con 422 cuando el avance no está permitido, de
 * modo que el frontend no replica ninguna de esas reglas.
 */
export async function advanceBatchPhase(
    batchId: string,
    toPhase: string,
    options: BatchRequestOptions = {},
): Promise<AdvancePhaseResponse> {
    return apiRequest<AdvancePhaseResponse>(`/batches/${batchId}/transitions`, {
        method: 'POST',
        token: accessToken(),
        signal: options.signal,
        body: { to_phase: toPhase },
    });
}
