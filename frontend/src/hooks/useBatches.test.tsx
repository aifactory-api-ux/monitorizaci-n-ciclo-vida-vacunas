import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, render, screen, waitFor } from '@testing-library/react';
import axios from 'axios';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { BatchList } from '@/components/dashboard/BatchList';
import { useBatches } from '@/hooks/useBatches';
import { useRealtimeEvents } from '@/hooks/useRealtimeEvents';
import { REALTIME_EVENT, type RealtimeClient } from '@/realtime/echo';
import { FakeRealtimeClient } from '@/test/fakeRealtimeClient';
import type { Batch, BatchSearchItem } from '@/types/batch';

function batchFixture(overrides: Partial<BatchSearchItem> = {}): BatchSearchItem {
    return {
        id: '6f1d0a9e-0d6a-4a9f-9d1a-0f3a2b7c4d51',
        reference_id: 'LOTE-2026-0001',
        reference_name: 'Vacuna recombinante',
        reference_family: 'Vacunas',
        phase: 'Fase de cultivo',
        status: 'En curso',
        current_campus_id: 'campus-1',
        created_at: '2026-01-01T08:00:00+00:00',
        updated_at: '2026-01-01T08:00:00+00:00',
        ...overrides,
    };
}

interface DashboardProps {
    client: RealtimeClient;
}

/**
 * Panel mínimo que reproduce el uso real: el listado de lotes y la
 * suscripción al canal de tiempo real.
 */
function Dashboard({ client }: DashboardProps) {
    const batches = useBatches();
    const { status } = useRealtimeEvents({ client });

    return (
        <section>
            <p>Estado de la conexión: {status}</p>

            {batches.isPending && <p>Cargando lotes…</p>}

            {batches.data?.batches.map((batch) => (
                <p key={batch.id}>
                    Lote {batch.reference_id}: {batch.phase} — {batch.status}
                </p>
            ))}
        </section>
    );
}

function renderDashboard(client: RealtimeClient) {
    const queryClient = new QueryClient({
        defaultOptions: { queries: { retry: false } },
    });

    const view = render(
        <QueryClientProvider client={queryClient}>
            <Dashboard client={client} />
        </QueryClientProvider>,
    );

    return { queryClient, ...view };
}

describe('useBatches con eventos de tiempo real', () => {
    let currentBatch: Batch;
    let client: FakeRealtimeClient;

    beforeEach(() => {
        currentBatch = batchFixture();
        client = new FakeRealtimeClient();

        vi.spyOn(axios, 'request').mockImplementation(async () => ({
            data: {
                batches: [currentBatch],
                total: 1,
                page: 1,
                per_page: 20,
                last_page: 1,
            },
        }));
    });

    it('invalida las consultas afectadas cuando llega un cambio de fase', async () => {
        const { queryClient } = renderDashboard(client);

        expect(await screen.findByText(/Lote LOTE-2026-0001/)).toBeInTheDocument();

        const invalidateQueries = vi.spyOn(queryClient, 'invalidateQueries');

        act(() => {
            client.emitEvent(REALTIME_EVENT.batchPhaseAdvanced, {
                batch_id: currentBatch.id,
                from_phase: 'Fase de cultivo',
                to_phase: 'Formulación Sphereon (Campus 2)',
                current_campus_id: 'campus-2',
            });
        });

        expect(invalidateQueries).toHaveBeenCalledWith({ queryKey: ['batches'] });
        expect(invalidateQueries).toHaveBeenCalledWith({ queryKey: ['batch'] });
        expect(invalidateQueries).toHaveBeenCalledWith({ queryKey: ['kpis'] });
        expect(invalidateQueries).toHaveBeenCalledWith({ queryKey: ['alerts'] });
    });

    it('refleja el nuevo estado del lote sin recargar la página', async () => {
        renderDashboard(client);

        expect(await screen.findByText(/Lote LOTE-2026-0001/)).toBeInTheDocument();

        currentBatch = batchFixture({
            phase: 'Formulación Sphereon (Campus 2)',
            status: 'En curso',
            current_campus_id: 'campus-2',
        });

        act(() => {
            client.emitEvent(REALTIME_EVENT.batchPhaseAdvanced, {
                batch_id: currentBatch.id,
                from_phase: 'Fase de cultivo',
                to_phase: 'Formulación Sphereon (Campus 2)',
                current_campus_id: 'campus-2',
            });
        });

        await waitFor(() => {
            expect(screen.getByText(/Lote LOTE-2026-0001/)).toHaveTextContent(
                'Formulación Sphereon (Campus 2)',
            );
        });
    });

    it('recupera el estado vigente del lote tras un corte de red y una reconexión', async () => {
        const { queryClient } = renderDashboard(client);

        expect(await screen.findByText(/Lote LOTE-2026-0001/)).toBeInTheDocument();

        act(() => {
            client.emitDisconnected();
        });

        expect(screen.getByText('Estado de la conexión: disconnected')).toBeInTheDocument();

        // El lote avanza mientras el cliente está sin conexión.
        currentBatch = batchFixture({
            phase: 'Fase logística (Campus 3)',
            status: 'Cerrado',
            current_campus_id: 'campus-3',
        });

        const invalidateQueries = vi.spyOn(queryClient, 'invalidateQueries');

        act(() => {
            client.emitSubscribed();
        });

        expect(screen.getByText('Estado de la conexión: connected')).toBeInTheDocument();
        expect(invalidateQueries).toHaveBeenCalledWith({ queryKey: ['batches'] });
        expect(invalidateQueries).toHaveBeenCalledWith({ queryKey: ['batch'] });
        expect(invalidateQueries).toHaveBeenCalledWith({ queryKey: ['kpis'] });
        expect(invalidateQueries).toHaveBeenCalledWith({ queryKey: ['alerts'] });

        await waitFor(() => {
            expect(screen.getByText(/Lote LOTE-2026-0001/)).toHaveTextContent(
                'Fase logística (Campus 3)',
            );
        });

        expect(screen.getByText(/Lote LOTE-2026-0001/)).toHaveTextContent('Cerrado');
    });
});

describe('BatchList', () => {
    let client: FakeRealtimeClient;

    beforeEach(() => {
        client = new FakeRealtimeClient();

        vi.spyOn(axios, 'request').mockImplementation(async () => ({
            data: {
                batches: [
                    batchFixture(),
                    batchFixture({
                        id: '8a2c1b7d-4e5f-4c6a-9b8d-1e2f3a4b5c6d',
                        reference_id: 'LOTE-2026-0002',
                        reference_name: 'Vacuna oral',
                        reference_family: 'Vacunas orales',
                        phase: 'Fase logística (Campus 3)',
                        status: 'Cerrado',
                    }),
                ],
                total: 12,
                page: 1,
                per_page: 2,
                last_page: 6,
            },
        }));
    });

    it('renderiza el listado a partir de la respuesta paginada', async () => {
        const queryClient = new QueryClient({
            defaultOptions: { queries: { retry: false } },
        });

        render(
            <QueryClientProvider client={queryClient}>
                <MemoryRouter>
                    <BatchList realtimeClient={client} />
                </MemoryRouter>
            </QueryClientProvider>,
        );

        expect(await screen.findByTestId('batch-list')).toBeInTheDocument();

        const items = screen.getAllByTestId('batch-list-item');
        expect(items).toHaveLength(2);
        expect(items[0]).toHaveTextContent('LOTE-2026-0001');
        expect(items[0]).toHaveTextContent('Vacunas');
        expect(items[0]).toHaveTextContent('Fase de cultivo');
        expect(items[0]).toHaveTextContent('En curso');
        expect(items[1]).toHaveTextContent('LOTE-2026-0002');
        expect(items[1]).toHaveTextContent('Cerrado');

        expect(screen.getByTestId('batch-list-summary')).toHaveTextContent('Página 1 de 6');
        expect(screen.getByTestId('batch-list-summary')).toHaveTextContent('12 lotes');
    });

    it('enlaza cada fila con la página de detalle del lote', async () => {
        const queryClient = new QueryClient({
            defaultOptions: { queries: { retry: false } },
        });

        render(
            <QueryClientProvider client={queryClient}>
                <MemoryRouter>
                    <BatchList realtimeClient={client} />
                </MemoryRouter>
            </QueryClientProvider>,
        );

        const link = await screen.findByRole('link', { name: 'LOTE-2026-0001' });

        expect(link).toHaveAttribute('href', '/batches/6f1d0a9e-0d6a-4a9f-9d1a-0f3a2b7c4d51');
    });
});
