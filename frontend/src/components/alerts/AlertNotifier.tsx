import { useMemo, useState } from 'react';

import { useAlerts } from '@/hooks/useAlerts';
import { useRealtimeEvents } from '@/hooks/useRealtimeEvents';
import { REALTIME_EVENT, type RealtimeClient, type RealtimeEventPayload } from '@/realtime/echo';
import { CRITICAL_SEVERITY, type Alert } from '@/types/alert';

export interface AlertNotifierProps {
    /** Cliente de tiempo real que difunde las alertas del canal de monitorización. */
    realtimeClient?: RealtimeClient | null;
}

/**
 * Convierte la carga útil de un evento de alerta en la alerta publicada por la
 * API, de modo que la notificación usa la misma representación.
 */
function toAlert(payload: RealtimeEventPayload): Alert {
    const severity = payload.severity ?? '';

    return {
        id: payload.alert_id ?? '',
        batch_id: payload.batch_id ?? '',
        alert_type: payload.alert_type ?? '',
        severity,
        status: severity,
        message: payload.message ?? '',
        created_at: payload.detected_at ?? '',
    };
}

/**
 * Notificador de alertas críticas del dashboard.
 *
 * Muestra el número de alertas críticas pendientes de atender y una
 * notificación visual por cada alerta crítica recibida en el canal de tiempo
 * real, sin que el usuario tenga que recargar la página (véase D11).
 */
export function AlertNotifier({ realtimeClient = null }: AlertNotifierProps) {
    const alerts = useAlerts();
    const [receivedAlerts, setReceivedAlerts] = useState<Alert[]>([]);

    useRealtimeEvents({
        client: realtimeClient,
        onEvent: (name, payload) => {
            if (name !== REALTIME_EVENT.alertRaised) {
                return;
            }

            const alert = toAlert(payload);

            setReceivedAlerts((current) =>
                current.some((item) => item.id === alert.id) ? current : [...current, alert],
            );
        },
    });

    const pendingAlerts = useMemo(() => {
        const alertsById = new Map<string, Alert>();

        alerts.data?.alerts.forEach((alert) => alertsById.set(alert.id, alert));
        receivedAlerts.forEach((alert) => {
            if (!alertsById.has(alert.id)) {
                alertsById.set(alert.id, alert);
            }
        });

        return [...alertsById.values()].filter((alert) => alert.severity === CRITICAL_SEVERITY);
    }, [alerts.data, receivedAlerts]);

    return (
        <section aria-labelledby="alert-notifier-title" className="flex flex-col gap-3">
            <h2 id="alert-notifier-title" className="text-title-20 text-text-high">
                Alertas críticas pendientes
            </h2>

            <p className="text-body-14 text-text-medium">
                Alertas críticas pendientes:{' '}
                <span data-testid="pending-alert-count" className="text-metric-24 numeric text-text-high">
                    {pendingAlerts.length}
                </span>
            </p>

            <ul data-testid="pending-alert-list" className="flex flex-col gap-2">
                {pendingAlerts.map((alert) => (
                    <li
                        key={alert.id}
                        role="alert"
                        data-testid="pending-alert"
                        className="rounded-lg border border-danger bg-danger-soft px-4 py-3"
                    >
                        <p className="text-body-14 font-medium text-danger">{alert.alert_type}</p>
                        <p className="text-body-14 text-danger">{alert.message}</p>
                        <p className="text-label-12 text-danger">Lote {alert.batch_id}</p>
                    </li>
                ))}
            </ul>
        </section>
    );
}
