import { accessToken } from '@/auth/authStore';
import { apiRequest } from '@/api/httpClient';
import type { TraceabilityReport } from '@/types/traceability';

/**
 * Formatos en los que el backend publica el reporte de trazabilidad.
 */
export type TraceabilityReportFormat = 'csv' | 'pdf';

export interface ReportRequestOptions {
    signal?: AbortSignal;
}

/**
 * Obtiene el reporte de trazabilidad en JSON.
 */
export async function fetchTraceabilityReport(
    batchId: string,
    options: ReportRequestOptions = {},
): Promise<TraceabilityReport> {
    return apiRequest<TraceabilityReport>(`/reports/traceability/${batchId}`, {
        token: accessToken(),
        signal: options.signal,
    });
}

/**
 * Descarga el reporte de trazabilidad en el formato indicado.
 *
 * El backend responde con el fichero en binario y la cabecera
 * `Content-Disposition: attachment`, de modo que la petición se realiza con
 * `responseType: 'blob'` (véase D15).
 */
export async function downloadTraceabilityReport(
    batchId: string,
    format: TraceabilityReportFormat,
    options: ReportRequestOptions = {},
): Promise<Blob> {
    return apiRequest<Blob>(`/reports/traceability/${batchId}.${format}`, {
        token: accessToken(),
        signal: options.signal,
        responseType: 'blob',
    });
}
