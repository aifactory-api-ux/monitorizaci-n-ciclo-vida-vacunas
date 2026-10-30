import { accessToken } from '@/auth/authStore';
import { apiRequest } from '@/api/httpClient';
import type { AlertsResponse } from '@/types/alert';

export interface AlertRequestOptions {
    signal?: AbortSignal;
}

/**
 * Obtiene todas las alertas registradas.
 */
export async function fetchAlerts(options: AlertRequestOptions = {}): Promise<AlertsResponse> {
    return apiRequest<AlertsResponse>('/alerts', {
        token: accessToken(),
        signal: options.signal,
    });
}

/**
 * Obtiene el histórico de alertas, opcionalmente acotado a un lote.
 */
export async function fetchAlertHistory(
    batchId?: string,
    options: AlertRequestOptions = {},
): Promise<AlertsResponse> {
    return apiRequest<AlertsResponse>('/alerts/history', {
        token: accessToken(),
        signal: options.signal,
        query: { batch_id: batchId },
    });
}

/**
 * Obtiene las alertas asociadas a un lote.
 */
export async function fetchBatchAlerts(
    batchId: string,
    options: AlertRequestOptions = {},
): Promise<AlertsResponse> {
    return apiRequest<AlertsResponse>(`/batches/${batchId}/alerts`, {
        token: accessToken(),
        signal: options.signal,
    });
}
