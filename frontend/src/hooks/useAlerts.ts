import { useQuery } from '@tanstack/react-query';

import { fetchAlertHistory, fetchAlerts } from '@/api/alerts';

/**
 * Clave raíz de las consultas de alertas.
 *
 * La invalidación por evento de tiempo real usa esta clave, de modo que el
 * histórico se recarga cuando el backend difunde una alerta (véase D12).
 */
export const ALERTS_QUERY_KEY = 'alerts';

/**
 * Consulta todas las alertas registradas.
 */
export function useAlerts() {
    return useQuery({
        queryKey: [ALERTS_QUERY_KEY],
        queryFn: ({ signal }) => fetchAlerts({ signal }),
    });
}

/**
 * Consulta el histórico de alertas, opcionalmente acotado a un lote.
 */
export function useAlertHistory(batchId?: string) {
    return useQuery({
        queryKey: [ALERTS_QUERY_KEY, 'history', batchId ?? null],
        queryFn: ({ signal }) => fetchAlertHistory(batchId, { signal }),
    });
}
