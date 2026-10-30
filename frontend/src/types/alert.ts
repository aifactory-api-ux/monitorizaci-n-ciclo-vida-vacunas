/**
 * Alerta operativa de prioridad máxima generada por el backend.
 *
 * El campo `status` replica la severidad persistida, porque la entidad
 * `ALERT` no tiene una columna de estado propia (véase D6).
 */
export interface Alert {
    id: string;
    batch_id: string;
    alert_type: string;
    severity: string;
    status: string;
    message: string;
    created_at: string;
}

/**
 * Envoltura del listado de alertas devuelto por la API.
 */
export interface AlertsResponse {
    alerts: Alert[];
}

/**
 * Severidad de prioridad máxima.
 */
export const CRITICAL_SEVERITY = 'Crítica';
