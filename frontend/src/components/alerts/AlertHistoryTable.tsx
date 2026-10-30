import type { Alert } from '@/types/alert';

export interface AlertHistoryTableProps {
    alerts: Alert[];
    caption?: string;
}

/**
 * Histórico de alertas con el tipo, el lote afectado, la fecha/hora y el estado.
 */
export function AlertHistoryTable({ alerts, caption = 'Histórico de alertas' }: AlertHistoryTableProps) {
    return (
        <table data-testid="alert-history-table" className="w-full text-left text-body-13">
            <caption className="pb-2 text-body-13 font-medium text-text-medium">{caption}</caption>
            <thead>
                <tr className="border-b border-line text-text-low">
                    <th scope="col" className="px-3 py-2 text-label-12 uppercase tracking-wide text-text-low">
                        Tipo
                    </th>
                    <th scope="col" className="px-3 py-2 text-label-12 uppercase tracking-wide text-text-low">
                        Lote afectado
                    </th>
                    <th scope="col" className="px-3 py-2 text-label-12 uppercase tracking-wide text-text-low">
                        Fecha/hora
                    </th>
                    <th scope="col" className="px-3 py-2 text-label-12 uppercase tracking-wide text-text-low">
                        Estado
                    </th>
                </tr>
            </thead>
            <tbody>
                {alerts.length === 0 ? (
                    <tr>
                        <td colSpan={4} className="px-3 py-4 text-text-medium">
                            No hay alertas registradas.
                        </td>
                    </tr>
                ) : (
                    alerts.map((alert) => (
                        <tr key={alert.id} data-testid="alert-history-row" className="border-b border-line">
                            <td className="px-3 py-2">{alert.alert_type}</td>
                            <td className="px-3 py-2">{alert.batch_id}</td>
                            <td className="px-3 py-2">
                                <time dateTime={alert.created_at}>{alert.created_at}</time>
                            </td>
                            <td className="px-3 py-2">{alert.status}</td>
                        </tr>
                    ))
                )}
            </tbody>
        </table>
    );
}
