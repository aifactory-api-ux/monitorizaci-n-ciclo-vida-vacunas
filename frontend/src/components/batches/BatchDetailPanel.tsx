import { useMemo } from 'react';

import { useBatchTraceability } from '@/hooks/useBatchTraceability';
import { useRealtimeEvents } from '@/hooks/useRealtimeEvents';
import type { RealtimeClient } from '@/realtime/echo';
import type { Traceability } from '@/types/traceability';

export interface BatchDetailPanelProps {
    /** Lote del que se muestra la trazabilidad. */
    batchId: string;
    /** Cliente de tiempo real que invalida la trazabilidad ante cambios. */
    realtimeClient?: RealtimeClient | null;
}

/**
 * Origen de un evento dentro de la línea temporal de trazabilidad.
 */
type TimelineEventKind = 'transition' | 'entry' | 'alert';

interface TimelineEvent {
    id: string;
    kind: TimelineEventKind;
    occurredAt: string;
    description: string;
}

/**
 * Construye la línea temporal del lote a partir de la trazabilidad.
 *
 * Fusiona las transiciones de fase, las entradas de auditoría y las alertas,
 * y las ordena cronológicamente. La ordenación es de presentación: no se
 * deduce ninguna regla de negocio a partir de ella.
 */
export function buildTimeline(traceability: Traceability): TimelineEvent[] {
    const events: TimelineEvent[] = [];

    traceability.transitions.forEach((transition) => {
        events.push({
            id: transition.id,
            kind: 'transition',
            occurredAt: transition.transition_date,
            description: `${transition.from_phase} → ${transition.to_phase}`,
        });
    });

    traceability.entries.forEach((entry) => {
        events.push({
            id: entry.id,
            kind: 'entry',
            occurredAt: entry.created_at,
            description: entry.event_type,
        });
    });

    traceability.alerts.forEach((alert) => {
        events.push({
            id: alert.id,
            kind: 'alert',
            occurredAt: alert.created_at,
            description: `${alert.alert_type}: ${alert.message}`,
        });
    });

    return events.sort((left, right) => Date.parse(left.occurredAt) - Date.parse(right.occurredAt));
}

/**
 * Detalle de trazabilidad de un lote.
 */
export function BatchDetailPanel({ batchId, realtimeClient = null }: BatchDetailPanelProps) {
    const traceability = useBatchTraceability(batchId);
    useRealtimeEvents({ client: realtimeClient });

    const events = useMemo(
        () => (traceability.data ? buildTimeline(traceability.data) : []),
        [traceability.data],
    );

    if (traceability.isPending) {
        return (
            <p className="text-body-14 text-text-medium" data-testid="batch-detail-loading">
                Cargando trazabilidad…
            </p>
        );
    }

    if (traceability.isError || traceability.data === undefined) {
        return (
            <p role="alert" data-testid="batch-detail-error" className="text-body-14 text-danger">
                No se ha podido cargar la trazabilidad del lote.
            </p>
        );
    }

    return (
        <section aria-labelledby="trazabilidad-lote" className="flex flex-col gap-4">
            <h2 id="trazabilidad-lote" className="text-title-20 text-text-high">
                Trazabilidad
            </h2>

            <dl className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <dt className="text-label-12 uppercase tracking-wide text-text-low">Fase</dt>
                    <dd data-testid="batch-detail-phase" className="text-body-14 text-text-high">
                        {traceability.data.phase}
                    </dd>
                </div>
                <div>
                    <dt className="text-label-12 uppercase tracking-wide text-text-low">Estado</dt>
                    <dd data-testid="batch-detail-status" className="text-body-14 text-text-high">
                        {traceability.data.status}
                    </dd>
                </div>
                <div>
                    <dt className="text-label-12 uppercase tracking-wide text-text-low">Referencia</dt>
                    <dd data-testid="batch-detail-reference" className="text-body-14 text-text-high">
                        {traceability.data.batch.reference_id}
                    </dd>
                </div>
            </dl>

            {events.length === 0 ? (
                <p data-testid="batch-detail-empty" className="text-body-14 text-text-medium">
                    Todavía no hay eventos registrados para el lote.
                </p>
            ) : (
                <ol data-testid="traceability-timeline" className="flex flex-col gap-3">
                    {events.map((event) => (
                        <li
                            key={`${event.kind}-${event.id}`}
                            data-testid="traceability-event"
                            data-kind={event.kind}
                            className="rounded-lg border border-line bg-surface px-4 py-3 shadow-card"
                        >
                            <time
                                dateTime={event.occurredAt}
                                data-testid="traceability-event-timestamp"
                                className="block text-label-12 text-text-low"
                            >
                                {event.occurredAt}
                            </time>
                            <span data-testid="traceability-event-description" className="text-body-14 text-text-high">
                                {event.description}
                            </span>
                        </li>
                    ))}
                </ol>
            )}
        </section>
    );
}
