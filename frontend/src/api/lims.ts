import { accessToken } from '@/auth/authStore';
import { apiRequest } from '@/api/httpClient';
import type { LimsResult } from '@/types/batch';

export interface LimsRequestOptions {
    signal?: AbortSignal;
}

/**
 * Carga descartada durante la importación por ser inconsistente.
 */
export interface LimsRejection {
    error_code: string;
    error_detail: string;
    batch_reference: string | null;
}

/**
 * Resumen de una importación de resultados de LIMS Labware.
 */
export interface LimsImportSummary {
    imported_batches: number;
    imported_results: number;
    rejected_payloads: number;
    rejections: LimsRejection[];
}

/**
 * Resultado microbiológico que se registra manualmente.
 */
export interface RegisterLimsResultPayload {
    /** Tipo de análisis registrado. */
    test_type: string;
    /** Literal del resultado. */
    result: string;
    /** Identificador de quien aprueba el resultado, si lo hay. */
    approved_by?: string | null;
    /** Fecha del resultado en formato ISO 8601. */
    result_date: string;
}

/**
 * Importa las cargas de resultados disponibles en LIMS Labware.
 *
 * La importación es de sólo lectura respecto de LIMS Labware: el backend
 * recoge las cargas y persiste las que satisfacen sus reglas de consistencia.
 * Las cargas descartadas se devuelven en el resumen con su motivo (véase D7
 * y D8).
 *
 * @param batchId Lote concreto a importar; `null` importa todas las cargas.
 */
export async function importLimsResults(
    batchId: string | null = null,
    options: LimsRequestOptions = {},
): Promise<LimsImportSummary> {
    const response = await apiRequest<{ summary: LimsImportSummary }>('/lims/import', {
        method: 'POST',
        token: accessToken(),
        signal: options.signal,
        body: { batch_id: batchId },
    });

    return response.summary;
}

/**
 * Registra un resultado microbiológico indicado manualmente.
 *
 * El backend valida los datos del resultado y evalúa su consistencia con el
 * resto de resultados del lote.
 */
export async function registerLimsResult(
    batchId: string,
    payload: RegisterLimsResultPayload,
    options: LimsRequestOptions = {},
): Promise<LimsResult> {
    return apiRequest<LimsResult>(`/batches/${batchId}/lims-results`, {
        method: 'POST',
        token: accessToken(),
        signal: options.signal,
        body: {
            test_type: payload.test_type,
            result: payload.result,
            approved_by: payload.approved_by ?? null,
            result_date: payload.result_date,
        },
    });
}
