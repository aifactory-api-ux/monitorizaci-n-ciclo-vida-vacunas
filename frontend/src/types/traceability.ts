import type { Alert } from '@/types/alert';
import type { Batch, LimsResult } from '@/types/batch';

/**
 * Transición de fase registrada de forma inmutable.
 */
export interface PhaseTransition {
    id: string;
    batch_id: string;
    from_phase: string;
    to_phase: string;
    transition_date: string;
}

/**
 * Visita de un lote a un campus.
 */
export interface CampusVisit {
    phase: string;
    campus_id: string;
    campus_name: string;
    since: string;
}

/**
 * Entrada del log estructurado e inmutable de auditoría.
 */
export interface LogEntry {
    id: string;
    batch_id: string | null;
    user_id: string | null;
    event_type: string;
    payload: Record<string, unknown>;
    created_at: string;
}

/**
 * Trazabilidad completa de un lote entre campus.
 */
export interface Traceability {
    batch_id: string;
    phase: string;
    status: string;
    current_campus_id: string;
    batch: Batch;
    campus_sequence: CampusVisit[];
    transitions: PhaseTransition[];
    lims_results: LimsResult[];
    alerts: Alert[];
    entries: LogEntry[];
}

/**
 * Reporte de trazabilidad de un lote, con la referencia de vacuna.
 */
export interface TraceabilityReport extends Traceability {
    reference_id: string;
    reference_name: string;
    reference_family: string;
}
