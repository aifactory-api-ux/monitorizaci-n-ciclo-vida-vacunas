import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, render, screen, waitFor } from '@testing-library/react';
import axios from 'axios';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { KpiPanel } from '@/components/dashboard/KpiPanel';
import { REALTIME_EVENT } from '@/realtime/echo';
import { FakeRealtimeClient } from '@/test/fakeRealtimeClient';
import type { DashboardKpis } from '@/types/kpi';

function kpisFixture(): DashboardKpis {
    return {
        cycle_time: {
            total_batches: 3,
            by_phase: [
                {
                    phase: 'Fase de cultivo',
                    average_seconds: 151200,
                    average_hours: 42,
                    sample_size: 2,
                    in_progress_count: 1,
                },
                {
                    phase: 'Formulación Sphereon (Campus 2)',
                    average_seconds: null,
                    average_hours: null,
                    sample_size: 0,
                    in_progress_count: 1,
                },
                {
                    phase: 'Fase logística (Campus 3)',
                    average_seconds: 86400,
                    average_hours: 24,
                    sample_size: 1,
                    in_progress_count: 1,
                },
            ],
        },
        status_breakdown: [
            { status: 'En curso', count: 2, percentage: 66.67 },
            { status: 'Cerrado', count: 1, percentage: 33.33 },
        ],
    };
}

function renderKpiPanel(client: FakeRealtimeClient) {
    const queryClient = new QueryClient({
        defaultOptions: { queries: { retry: false } },
    });

    return render(
        <QueryClientProvider client={queryClient}>
            <KpiPanel realtimeClient={client} />
        </QueryClientProvider>,
    );
}

describe('KpiPanel', () => {
    let currentKpis: DashboardKpis;
    let client: FakeRealtimeClient;

    beforeEach(() => {
        currentKpis = kpisFixture();
        client = new FakeRealtimeClient();

        vi.spyOn(axios, 'request').mockImplementation(async () => ({
            data: { kpis: currentKpis },
        }));
    });

    it('pinta los indicadores devueltos por la API', async () => {
        renderKpiPanel(client);

        expect(await screen.findByTestId('kpi-total-batches')).toHaveTextContent('3');

        const cycleTimeRows = screen.getAllByTestId('kpi-cycle-time-row');
        expect(cycleTimeRows).toHaveLength(3);
        expect(cycleTimeRows[0]).toHaveTextContent('Fase de cultivo');
        expect(cycleTimeRows[0]).toHaveTextContent('42');
        expect(cycleTimeRows[0]).toHaveTextContent('151200');
        expect(cycleTimeRows[1]).toHaveTextContent('Formulación Sphereon (Campus 2)');
        expect(cycleTimeRows[1]).toHaveTextContent('—');

        const breakdownRows = screen.getAllByTestId('kpi-status-breakdown-row');
        expect(breakdownRows).toHaveLength(2);
        expect(breakdownRows[0]).toHaveTextContent('En curso');
        expect(breakdownRows[0]).toHaveTextContent('66.67 %');
        expect(breakdownRows[1]).toHaveTextContent('Cerrado');
        expect(breakdownRows[1]).toHaveTextContent('33.33 %');
    });

    it('vuelve a pintar los indicadores cuando llega un evento de tiempo real', async () => {
        renderKpiPanel(client);

        expect(await screen.findByTestId('kpi-total-batches')).toHaveTextContent('3');

        currentKpis = {
            ...kpisFixture(),
            cycle_time: { ...kpisFixture().cycle_time, total_batches: 5 },
        };

        act(() => {
            client.emitEvent(REALTIME_EVENT.batchStatusChanged, {
                batch_id: '6f1d0a9e-0d6a-4a9f-9d1a-0f3a2b7c4d51',
                status: 'Cerrado',
            });
        });

        await waitFor(() => {
            expect(screen.getByTestId('kpi-total-batches')).toHaveTextContent('5');
        });
    });
});
