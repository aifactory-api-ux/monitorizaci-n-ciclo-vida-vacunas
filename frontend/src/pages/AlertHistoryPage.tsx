import { Link } from 'react-router-dom';

import { AlertHistoryTable } from '@/components/alerts/AlertHistoryTable';
import { AlertNotifier } from '@/components/alerts/AlertNotifier';
import { useAlertHistory } from '@/hooks/useAlerts';
import { useRealtimeClient } from '@/realtime/useRealtimeClient';

/**
 * Histórico de alertas del ciclo de vida de los lotes.
 */
export function AlertHistoryPage() {
    const realtimeClient = useRealtimeClient();
    const history = useAlertHistory();

    return (
        <main className="mx-auto flex min-h-screen max-w-5xl flex-col gap-7 px-6 py-9">
            <header className="flex flex-col gap-2">
                <h1 className="font-display text-display-28 tracking-tight text-text-high">Histórico de alertas</h1>
                <nav className="flex gap-4 text-body-14 text-text-medium">
                    <Link to="/">Panel</Link>
                    <Link to="/alerts">Histórico de alertas</Link>
                </nav>
            </header>

            <AlertNotifier realtimeClient={realtimeClient} />

            {history.isPending ? (
                <p className="text-body-14 text-text-medium" data-testid="alert-history-loading">
                    Cargando alertas…
                </p>
            ) : null}

            {history.isError ? (
                <p role="alert" data-testid="alert-history-error" className="text-body-14 text-danger">
                    No se ha podido consultar el histórico de alertas.
                </p>
            ) : null}

            {history.data ? <AlertHistoryTable alerts={history.data.alerts} /> : null}
        </main>
    );
}
