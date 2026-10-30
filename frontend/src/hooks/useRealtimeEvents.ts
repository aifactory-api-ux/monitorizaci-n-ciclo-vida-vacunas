import { useQueryClient } from '@tanstack/react-query';
import { useEffect, useRef, useState } from 'react';

import {
    BATCH_MONITORING_CHANNEL,
    type RealtimeClient,
    type RealtimeEventName,
    type RealtimeEventPayload,
} from '@/realtime/echo';

/**
 * Claves de consulta que se invalidan al recibir un evento de tiempo real.
 *
 * Son las cuatro lecturas que alimentan el panel: el listado de lotes, el
 * detalle de un lote, los indicadores y las alertas (véase D12).
 */
export const REALTIME_QUERY_KEYS = ['batches', 'batch', 'kpis', 'alerts'] as const;

/**
 * Estado de la conexión con el canal de tiempo real.
 */
export type RealtimeConnectionStatus = 'connecting' | 'connected' | 'disconnected';

export interface UseRealtimeEventsOptions {
    client?: RealtimeClient | null;
    channel?: string;
    onEvent?: (name: RealtimeEventName, payload: RealtimeEventPayload) => void;
}

export interface UseRealtimeEventsResult {
    status: RealtimeConnectionStatus;
}

/**
 * Suscribe la aplicación al canal de monitorización de lotes.
 *
 * Cada evento recibido invalida las consultas afectadas de TanStack Query,
 * de modo que la vista se actualiza con el estado que devuelve el backend
 * (véase D12).
 *
 * El cliente WebSocket reconecta solo tras un corte de red. Cuando la
 * suscripción vuelve a estar vigente se invalidan las mismas consultas, lo
 * que resincroniza el estado del lote con el backend y recupera los cambios
 * que pudieron ocurrir durante el corte (véase D11).
 */
export function useRealtimeEvents(options: UseRealtimeEventsOptions = {}): UseRealtimeEventsResult {
    const queryClient = useQueryClient();
    const { client = null, channel = BATCH_MONITORING_CHANNEL } = options;
    const [status, setStatus] = useState<RealtimeConnectionStatus>('connecting');
    const onEventRef = useRef(options.onEvent);

    useEffect(() => {
        onEventRef.current = options.onEvent;
    }, [options.onEvent]);

    useEffect(() => {
        if (!client) {
            setStatus('disconnected');

            return;
        }

        const invalidateAffectedQueries = (): void => {
            REALTIME_QUERY_KEYS.forEach((key) => {
                void queryClient.invalidateQueries({ queryKey: [key] });
            });
        };

        const unsubscribe = client.subscribe(channel, {
            onEvent: (name, payload) => {
                invalidateAffectedQueries();
                onEventRef.current?.(name, payload);
            },
            onSubscribed: () => {
                setStatus('connected');
                invalidateAffectedQueries();
            },
            onDisconnected: () => setStatus('disconnected'),
            onError: () => setStatus('disconnected'),
        });

        return unsubscribe;
    }, [client, channel, queryClient]);

    return { status };
}
