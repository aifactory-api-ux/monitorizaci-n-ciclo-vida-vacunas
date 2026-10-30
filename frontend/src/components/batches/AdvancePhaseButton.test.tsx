import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axios from 'axios';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { AdvancePhaseButton } from '@/components/batches/AdvancePhaseButton';
import { BatchDetailPanel } from '@/components/batches/BatchDetailPanel';
import { useBatchDetail } from '@/hooks/useBatchDetail';
import { useBatchTraceability } from '@/hooks/useBatchTraceability';
import type { BatchDetail } from '@/types/batch';
import type { Traceability } from '@/types/traceability';

const BATCH_ID = '6f1d0a9e-0d6a-4a9f-9d1a-0f3a2b7c4d51';
const NEXT_PHASE = 'Formulación Sphereon (Campus 2)';

function detailFixture(overrides: Partial<BatchDetail> = {}): BatchDetail {
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
        ...overrides,
    };
}

interface BatchAdvancePanelProps {
    onAdvance?: (toPhase: string) => void;
}

/**
 * Panel mínimo que reproduce el uso real: el detalle del lote obtenido de la
 * API y el botón de avance de fase.
 */
function BatchAdvancePanel({ onAdvance }: BatchAdvancePanelProps) {
    const detail = useBatchDetail(BATCH_ID);

    if (detail.isPending) {
        return <p>Cargando lote…</p>;
    }

    if (!detail.data) {
        return null;
    }

    return (
        <AdvancePhaseButton
            batch={detail.data.batch}
            gating={detail.data.gating}
            toPhase={NEXT_PHASE}
            onAdvance={onAdvance}
        />
    );
}

function renderBatchAdvancePanel(onAdvance?: (toPhase: string) => void) {
    const queryClient = new QueryClient({
        defaultOptions: { queries: { retry: false } },
    });

    return render(
        <QueryClientProvider client={queryClient}>
            <BatchAdvancePanel onAdvance={onAdvance} />
        </QueryClientProvider>,
    );
}

describe('AdvancePhaseButton', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
    });

    it('deshabilita el avance y muestra el aviso cuando la API devuelve el lote bloqueado por gating', async () => {
        vi.spyOn(axios, 'request').mockImplementation(async () => ({
            data: detailFixture({
                batch: {
                    ...detailFixture().batch,
                    status: 'Bloqueado por Gating LIMS',
                },
                gating: {
                    ...detailFixture().gating,
                    approved: false,
                    reason: 'El lote no tiene ningún resultado microbiológico registrado.',
                    approved_by: null,
                    source: null,
                    updated_at: null,
                },
            }),
        }));

        renderBatchAdvancePanel();

        const button = await screen.findByTestId('advance-phase-button');
        expect(button).toBeDisabled();

        const notice = screen.getByTestId('gating-block-notice');
        expect(notice).toHaveTextContent('Bloqueado por Gating LIMS');
        expect(notice).toHaveTextContent(
            'El lote no tiene ningún resultado microbiológico registrado.',
        );
    });

    it('mantiene el avance habilitado cuando la API no devuelve el lote bloqueado', async () => {
        vi.spyOn(axios, 'request').mockImplementation(async () => ({
            data: detailFixture(),
        }));

        renderBatchAdvancePanel();

        expect(await screen.findByTestId('advance-phase-button')).toBeEnabled();
        expect(screen.queryByTestId('gating-block-notice')).not.toBeInTheDocument();
    });

    it('no recalcula la regla de gating y sólo representa el estado devuelto por la API', async () => {
        vi.spyOn(axios, 'request').mockImplementation(async () => ({
            data: detailFixture({
                gating: {
                    ...detailFixture().gating,
                    approved: false,
                    reason: 'El lote no tiene ningún resultado microbiológico registrado.',
                },
            }),
        }));

        renderBatchAdvancePanel();

        expect(await screen.findByTestId('advance-phase-button')).toBeEnabled();
        expect(screen.queryByTestId('gating-block-notice')).not.toBeInTheDocument();
    });

    it('solicita el avance hacia la fase destino', async () => {
        vi.spyOn(axios, 'request').mockImplementation(async () => ({
            data: detailFixture(),
        }));

        const onAdvance = vi.fn();
        renderBatchAdvancePanel(onAdvance);

        const button = await screen.findByTestId('advance-phase-button');
        await userEvent.click(button);

        expect(onAdvance).toHaveBeenCalledWith(NEXT_PHASE);
    });
});

function traceabilityFixture(): Traceability {
    return {
        batch_id: BATCH_ID,
        phase: 'Fase de cultivo',
        status: 'En curso',
        current_campus_id: 'campus-1',
        batch: detailFixture().batch,
        campus_sequence: [],
        transitions: [
            {
                id: '3c4d5e6f-0000-4000-8000-000000000003',
                batch_id: BATCH_ID,
                from_phase: 'Fase de cultivo',
                to_phase: NEXT_PHASE,
                transition_date: '2026-01-06T08:00:00+00:00',
            },
        ],
        lims_results: [],
        alerts: [
            {
                id: '3c4d5e6f-0000-4000-8000-000000000002',
                batch_id: BATCH_ID,
                alert_type: 'Desviación térmica',
                severity: 'Crítica',
                status: 'Crítica',
                message: 'Desviación térmica de 4.20 °C.',
                created_at: '2026-01-05T09:30:00+00:00',
            },
        ],
        entries: [
            {
                id: '3c4d5e6f-0000-4000-8000-000000000001',
                batch_id: BATCH_ID,
                user_id: null,
                event_type: 'batch.created',
                payload: {},
                created_at: '2026-01-05T08:00:00+00:00',
            },
        ],
    };
}

function BatchTraceabilityPanel() {
    const traceability = useBatchTraceability(BATCH_ID);

    if (traceability.isPending) {
        return <p>Cargando trazabilidad…</p>;
    }

    return <BatchDetailPanel batchId={BATCH_ID} />;
}

function renderBatchTraceabilityPanel() {
    const queryClient = new QueryClient({
        defaultOptions: { queries: { retry: false } },
    });

    return render(
        <QueryClientProvider client={queryClient}>
            <BatchTraceabilityPanel />
        </QueryClientProvider>,
    );
}

describe('BatchDetailPanel', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
    });

    it('ordena cronológicamente los eventos de trazabilidad del lote', async () => {
        vi.spyOn(axios, 'request').mockImplementation(async () => ({
            data: traceabilityFixture(),
        }));

        renderBatchTraceabilityPanel();

        const timestamps = await screen.findAllByTestId('traceability-event-timestamp');

        expect(timestamps.map((timestamp) => timestamp.textContent)).toEqual([
            '2026-01-05T08:00:00+00:00',
            '2026-01-05T09:30:00+00:00',
            '2026-01-06T08:00:00+00:00',
        ]);

        const descriptions = screen.getAllByTestId('traceability-event-description');
        expect(descriptions[0]).toHaveTextContent('batch.created');
        expect(descriptions[1]).toHaveTextContent('Desviación térmica');
        expect(descriptions[2]).toHaveTextContent('Fase de cultivo → Formulación Sphereon (Campus 2)');
    });
});
