import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import axios from 'axios';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import App from '@/App';
import { clearAccessToken, setAccessToken } from '@/auth/authStore';
import type { BatchDetail, BatchSearchItem } from '@/types/batch';
import type { Traceability } from '@/types/traceability';

const BATCH_ID = '6f1d0a9e-0d6a-4a9f-9d1a-0f3a2b7c4d51';

function batchFixture(): BatchSearchItem {
    return {
        id: BATCH_ID,
        reference_id: 'LOTE-2026-0001',
        reference_name: 'Vacuna recombinante',
        reference_family: 'Vacunas',
        phase: 'Fase de cultivo',
        status: 'En curso',
        current_campus_id: 'campus-1',
        created_at: '2026-01-05T08:00:00+00:00',
        updated_at: '2026-01-05T08:00:00+00:00',
    };
}

function detailFixture(): BatchDetail {
    return {
        batch: {
            id: BATCH_ID,
            reference_id: 'LOTE-2026-0001',
            phase: 'Fase de cultivo',
            status: 'En curso',
            current_campus_id: 'campus-1',
            created_at: '2026-01-05T08:00:00+00:00',
            updated_at: '2026-01-05T08:00:00+00:00',
        },
        lims_results: [],
        gating: {
            batch_id: BATCH_ID,
            approved: true,
            reason: null,
            approved_by: 'Responsable LIMS',
            source: 'LIMS Labware',
            updated_at: '2026-01-06T09:00:00+00:00',
        },
    };
}

function traceabilityFixture(): Traceability {
    return {
        batch_id: BATCH_ID,
        phase: 'Fase de cultivo',
        status: 'En curso',
        current_campus_id: 'campus-1',
        batch: detailFixture().batch,
        campus_sequence: [],
        transitions: [],
        lims_results: [],
        alerts: [],
        entries: [],
    };
}

function renderApp(path: string) {
    window.history.pushState({}, '', path);

    const queryClient = new QueryClient({
        defaultOptions: { queries: { retry: false } },
    });

    return render(
        <QueryClientProvider client={queryClient}>
            <App />
        </QueryClientProvider>,
    );
}

describe('App', () => {
    beforeEach(() => {
        vi.spyOn(axios, 'request').mockImplementation(async (config) => {
            const url = String((config as { url?: string } | undefined)?.url ?? '');

            if (url.includes('/dashboard/kpis')) {
                return {
                    data: {
                        kpis: {
                            cycle_time: {
                                total_batches: 1,
                                by_phase: [
                                    {
                                        phase: 'Fase de cultivo',
                                        average_seconds: 151200,
                                        average_hours: 42,
                                        sample_size: 1,
                                        in_progress_count: 1,
                                    },
                                ],
                            },
                            status_breakdown: [{ status: 'En curso', count: 1, percentage: 100 }],
                        },
                    },
                };
            }

            if (url.includes('/search/batches')) {
                return {
                    data: {
                        batches: [batchFixture()],
                        total: 1,
                        page: 1,
                        per_page: 20,
                        last_page: 1,
                    },
                };
            }

            if (url.includes('/traceability')) {
                return { data: traceabilityFixture() };
            }

            if (url.includes('/alerts')) {
                return { data: { alerts: [] } };
            }

            if (url.includes('/health')) {
                return {
                    data: {
                        status: 'ok',
                        service: 'Vaccine Batch Tracking',
                        environment: 'testing',
                        timestamp: '2026-01-01T00:00:00Z',
                        checks: { database: { status: 'ok', driver: 'sqlite' } },
                    },
                };
            }

            if (url.includes(`/batches/${BATCH_ID}`)) {
                return { data: detailFixture() };
            }

            return { data: {} };
        });
    });

    afterEach(() => {
        clearAccessToken();
    });

    it('redirige a la autenticación cuando no hay token de acceso', async () => {
        renderApp(`/batches/${BATCH_ID}`);

        expect(
            await screen.findByText('Inicia sesión para acceder al panel de monitorización.'),
        ).toBeInTheDocument();
        expect(window.location.pathname).toBe('/login');
    });

    it('muestra el panel principal con los indicadores y el listado de lotes', async () => {
        setAccessToken('token-de-prueba');

        renderApp('/');

        expect(await screen.findByTestId('kpi-total-batches')).toHaveTextContent('1');
        expect(screen.getByTestId('batch-list-summary')).toHaveTextContent('1 lotes');
    });

    it('muestra el detalle del lote solicitado', async () => {
        setAccessToken('token-de-prueba');

        renderApp(`/batches/${BATCH_ID}`);

        expect(await screen.findByTestId('batch-phase')).toHaveTextContent('Fase de cultivo');
        expect(screen.getByTestId('batch-status')).toHaveTextContent('En curso');
        expect(screen.getByTestId('advance-phase-button')).toBeInTheDocument();
    });

    it('muestra el histórico de alertas', async () => {
        setAccessToken('token-de-prueba');

        renderApp('/alerts');

        expect(await screen.findByTestId('alert-history-table')).toBeInTheDocument();
    });
});
