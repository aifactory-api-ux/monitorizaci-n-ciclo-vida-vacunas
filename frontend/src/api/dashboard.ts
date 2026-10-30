import { accessToken } from '@/auth/authStore';
import { apiRequest } from '@/api/httpClient';
import type { DashboardKpis } from '@/types/kpi';

export interface DashboardRequestOptions {
    signal?: AbortSignal;
}

/**
 * Obtiene los indicadores del dashboard.
 *
 * La respuesta de la API envuelve el bloque de indicadores en la clave
 * `kpis`, de modo que la capa de API devuelve ese bloque ya desenvuelto.
 */
export async function fetchDashboardKpis(
    options: DashboardRequestOptions = {},
): Promise<DashboardKpis> {
    const response = await apiRequest<{ kpis: DashboardKpis }>('/dashboard/kpis', {
        token: accessToken(),
        signal: options.signal,
    });

    return response.kpis;
}
