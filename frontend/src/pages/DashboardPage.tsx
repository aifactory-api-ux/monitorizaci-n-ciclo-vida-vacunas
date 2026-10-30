import { useState } from 'react';
import { Link } from 'react-router-dom';

import { AlertNotifier } from '@/components/alerts/AlertNotifier';
import { BatchList } from '@/components/dashboard/BatchList';
import { BatchFilters, type BatchFilterValue } from '@/components/filters/BatchFilters';
import { HealthPanel } from '@/components/health/HealthPanel';
import { KpiPanel } from '@/components/dashboard/KpiPanel';
import { useRealtimeClient } from '@/realtime/useRealtimeClient';

const EMPTY_FILTERS: BatchFilterValue = { family: '', phase: '', alert_status: '' };

/**
 * Panel principal del dashboard de monitorización.
 */
export function DashboardPage() {
    const realtimeClient = useRealtimeClient();
    const [filters, setFilters] = useState<BatchFilterValue>(EMPTY_FILTERS);

    return (
        <main className="mx-auto flex min-h-screen max-w-5xl flex-col gap-7 px-6 py-9">
            <header className="flex flex-col gap-2">
                <h1 className="font-display text-display-28 tracking-tight text-text-high">Vaccine Batch Tracking</h1>
                <p className="text-body-14 text-text-medium">
                    Seguimiento de lotes de vacunas en el ciclo de producción, logística y calidad.
                </p>
                <nav className="flex gap-4 text-body-14 text-text-medium">
                    <Link to="/">Panel</Link>
                    <Link to="/alerts">Histórico de alertas</Link>
                </nav>
            </header>

            <AlertNotifier realtimeClient={realtimeClient} />

            <KpiPanel realtimeClient={realtimeClient} />

            <BatchFilters value={filters} onChange={setFilters} />

            <BatchList filters={filters} realtimeClient={realtimeClient} />

            <HealthPanel />
        </main>
    );
}
