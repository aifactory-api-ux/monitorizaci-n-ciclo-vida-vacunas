/**
 * Indicadores del dashboard publicados por `GET /api/v1/dashboard/kpis`.
 */

/**
 * Tiempo de ciclo de una fase del ciclo de vida del lote.
 */
export interface PhaseCycleTime {
    phase: string;
    average_seconds: number | null;
    average_hours: number | null;
    sample_size: number;
    in_progress_count: number;
}

/**
 * Bloque de tiempos de ciclo del dashboard.
 */
export interface CycleTime {
    by_phase: PhaseCycleTime[];
    total_batches: number;
}

/**
 * Peso de un estado de lote dentro del total.
 */
export interface StatusBreakdown {
    status: string;
    count: number;
    percentage: number;
}

/**
 * Indicadores del dashboard.
 */
export interface DashboardKpis {
    cycle_time: CycleTime;
    status_breakdown: StatusBreakdown[];
}
