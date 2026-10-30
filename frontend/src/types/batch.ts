/**
 * Representación de un lote de vacuna en el frontend.
 *
 * Los nombres de los campos son los que devuelve la API, de modo que el
 * frontend no traduce la respuesta ni calcula reglas de negocio.
 */
export interface Batch {
    id: string;
    reference_id: string;
    reference_name?: string;
    reference_family?: string;
    phase: string;
    status: string;
    current_campus_id: string;
    created_at: string;
    updated_at: string;
}

/**
 * Lote devuelto por la búsqueda avanzada, que además incluye la referencia
 * de vacuna y su familia de producto.
 */
export interface BatchSearchItem extends Batch {
    reference_name: string;
    reference_family: string;
}

/**
 * Página de lotes devuelta por la búsqueda avanzada.
 */
export interface BatchPage {
    batches: BatchSearchItem[];
    total: number;
    page: number;
    per_page: number;
    last_page: number;
}

/**
 * Estado de bloqueo por gating publicado por la API.
 *
 * El frontend sólo compara el estado devuelto con este literal: la regla de
 * gating vive en el backend (véase D5).
 */
export const GATING_BLOCKED_STATUS = 'Bloqueado por Gating LIMS';

/**
 * Estados del ciclo de vida del lote publicados por la API.
 *
 * El frontend sólo compara el estado devuelto con estos literales: las reglas
 * que los transicionan viven en el backend (véase D5).
 */
export const BATCH_STATUS_IN_PROGRESS = 'En curso';
export const BATCH_STATUS_RECEIVED = 'Recibido';
export const BATCH_STATUS_CLOSED = 'Cerrado';

/**
 * Fases del ciclo de vida del lote.
 *
 * Reflejan los literales del valor objeto `BatchPhase` del backend, que
 * valida las fases admitidas en el filtro de búsqueda.
 */
export const BATCH_PHASES = [
    'Fase de cultivo',
    'Formulación Sphereon (Campus 2)',
    'Fase logística (Campus 3)',
] as const;

/**
 * Estados de alerta admitidos por el filtro de búsqueda.
 *
 * Reflejan los literales del valor objeto `AlertStatus` del backend.
 */
export const ALERT_STATUSES = ['Con alerta crítica', 'Sin alerta crítica'] as const;

/**
 * Resultado microbiológico importado de LIMS.
 */
export interface LimsResult {
    id: string;
    batch_id: string;
    test_type: string;
    result: string;
    approved_by: string | null;
    result_date: string;
    source: string;
    updated_at: string;
}

/**
 * Decisión del gating microbiológico tal y como la resuelve el backend.
 */
export interface Gating {
    batch_id: string;
    approved: boolean;
    reason: string | null;
    approved_by: string | null;
    source: string | null;
    updated_at: string | null;
}

/**
 * Detalle de un lote con sus resultados de LIMS y su gating.
 */
export interface BatchDetail {
    batch: Batch;
    lims_results: LimsResult[];
    gating: Gating;
}
