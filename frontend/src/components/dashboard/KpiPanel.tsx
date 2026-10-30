import { useDashboardKpis } from '@/hooks/useDashboardKpis';
import { useRealtimeEvents } from '@/hooks/useRealtimeEvents';
import type { RealtimeClient } from '@/realtime/echo';

export interface KpiPanelProps {
    /** Cliente de tiempo real que refresca los indicadores al recibir eventos. */
    realtimeClient?: RealtimeClient | null;
}

/**
 * Presenta un número decimal con un máximo de dos decimales.
 */
function formatDecimal(value: number): string {
    return String(Math.round(value * 100) / 100);
}

/**
 * Panel de indicadores del ciclo de vida de los lotes.
 *
 * Los valores se pintan tal y como los devuelve la API; el panel no calcula
 * ningún indicador propio. Cada evento del canal de monitorización invalida la
 * consulta de indicadores, de modo que el panel se repinta con el estado
 * vigente del backend sin necesidad de recargar la página (véase D12).
 */
export function KpiPanel({ realtimeClient = null }: KpiPanelProps) {
    const kpis = useDashboardKpis();

    useRealtimeEvents({ client: realtimeClient });

    if (kpis.isPending) {
        return (
            <p className="text-body-14 text-text-medium" data-testid="kpi-loading">
                Consultando los indicadores del dashboard…
            </p>
        );
    }

    if (kpis.isError) {
        return (
            <p role="alert" className="text-body-14 text-danger" data-testid="kpi-error">
                No se han podido consultar los indicadores del dashboard.
            </p>
        );
    }

    if (!kpis.data) {
        return null;
    }

    const { cycle_time: cycleTime, status_breakdown: statusBreakdown } = kpis.data;

    return (
        <section aria-labelledby="kpi-panel-title" className="flex flex-col gap-6">
            <h2 id="kpi-panel-title" className="text-title-20 text-text-high">
                Indicadores del ciclo de vida
            </h2>

            <article className="rounded-lg border border-line bg-surface px-4 py-3 shadow-card">
                <p className="text-label-12 uppercase tracking-wide text-text-low">Lotes monitorizados</p>
                <p
                    data-testid="kpi-total-batches"
                    className="text-metric-24 numeric tracking-tight text-text-high"
                >
                    {cycleTime.total_batches}
                </p>
            </article>

            <table
                data-testid="kpi-cycle-time"
                className="w-full border-collapse text-left text-body-13 text-text-medium"
            >
                <caption className="pb-2 text-body-13 font-medium text-text-medium">
                    Tiempo de ciclo por fase
                </caption>
                <thead>
                    <tr className="border-b border-line">
                        <th scope="col" className="py-2 pr-4 text-label-12 uppercase tracking-wide text-text-low">
                            Fase
                        </th>
                        <th scope="col" className="py-2 pr-4 text-label-12 uppercase tracking-wide text-text-low">
                            Tiempo medio (h)
                        </th>
                        <th scope="col" className="py-2 pr-4 text-label-12 uppercase tracking-wide text-text-low">
                            Tiempo medio (s)
                        </th>
                        <th scope="col" className="py-2 pr-4 text-label-12 uppercase tracking-wide text-text-low">
                            Muestras
                        </th>
                        <th scope="col" className="py-2 text-label-12 uppercase tracking-wide text-text-low">
                            Lotes en fase
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {cycleTime.by_phase.map((phase) => (
                        <tr key={phase.phase} data-testid="kpi-cycle-time-row" className="border-b border-line">
                            <th scope="row" className="py-2 pr-4 font-medium text-text-high">
                                {phase.phase}
                            </th>
                            <td className="py-2 pr-4">
                                {phase.average_hours === null ? '—' : formatDecimal(phase.average_hours)}
                            </td>
                            <td className="py-2 pr-4">
                                {phase.average_seconds === null ? '—' : formatDecimal(phase.average_seconds)}
                            </td>
                            <td className="py-2 pr-4">{phase.sample_size}</td>
                            <td className="py-2">{phase.in_progress_count}</td>
                        </tr>
                    ))}
                </tbody>
            </table>

            <table
                data-testid="kpi-status-breakdown"
                className="w-full border-collapse text-left text-body-13 text-text-medium"
            >
                <caption className="pb-2 text-body-13 font-medium text-text-medium">
                    Distribución de lotes por estado
                </caption>
                <thead>
                    <tr className="border-b border-line">
                        <th scope="col" className="py-2 pr-4 text-label-12 uppercase tracking-wide text-text-low">
                            Estado
                        </th>
                        <th scope="col" className="py-2 pr-4 text-label-12 uppercase tracking-wide text-text-low">
                            Lotes
                        </th>
                        <th scope="col" className="py-2 text-label-12 uppercase tracking-wide text-text-low">
                            Porcentaje
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {statusBreakdown.map((breakdown) => (
                        <tr
                            key={breakdown.status}
                            data-testid="kpi-status-breakdown-row"
                            className="border-b border-line"
                        >
                            <th scope="row" className="py-2 pr-4 font-medium text-text-high">
                                {breakdown.status}
                            </th>
                            <td className="py-2 pr-4">{breakdown.count}</td>
                            <td className="py-2">{formatDecimal(breakdown.percentage)} %</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </section>
    );
}
