import { useQuery } from '@tanstack/react-query';

import { fetchDashboardKpis } from '@/api/dashboard';

/**
 * Clave raíz de las consultas de los indicadores del dashboard.
 *
 * La invalidación por evento de tiempo real usa esta clave, de modo que los
 * indicadores se recalculan con el estado que devuelve el backend (véase D12).
 */
export const DASHBOARD_KPIS_QUERY_KEY = 'kpis';

/**
 * Consulta los indicadores del dashboard.
 */
export function useDashboardKpis() {
    return useQuery({
        queryKey: [DASHBOARD_KPIS_QUERY_KEY],
        queryFn: ({ signal }) => fetchDashboardKpis({ signal }),
    });
}
