import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axios from 'axios';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { AlertHistoryTable } from '@/components/alerts/AlertHistoryTable';
import { AlertNotifier } from '@/components/alerts/AlertNotifier';
import { ExportTraceabilityReportButton } from '@/components/reports/ExportTraceabilityReportButton';
import { REALTIME_EVENT } from '@/realtime/echo';
import { FakeRealtimeClient } from '@/test/fakeRealtimeClient';
import type { Alert } from '@/types/alert';

function alertFixture(overrides: Partial<Alert> = {}): Alert {
    return {
        id: 'a1b2c3d4-0000-4000-8000-000000000001',
        batch_id: '6f1d0a9e-0d6a-4a9f-9d1a-0f3a2b7c4d51',
        alert_type: 'Desviación térmica',
        severity: 'Crítica',
        status: 'Crítica',
        message: 'Desviación térmica de 4.20 °C sobre el umbral permitido de 2.00 °C.',
        created_at: '2026-01-05T09:30:00+00:00',
        ...overrides,
    };
}

function renderAlertNotifier(client: FakeRealtimeClient) {
    const queryClient = new QueryClient({
        defaultOptions: { queries: { retry: false } },
    });

    return render(
        <QueryClientProvider client={queryClient}>
            <AlertNotifier realtimeClient={client} />
        </QueryClientProvider>,
    );
}

describe('AlertNotifier', () => {
    let client: FakeRealtimeClient;

    beforeEach(() => {
        client = new FakeRealtimeClient();

        vi.spyOn(axios, 'request').mockImplementation(async () => ({
            data: { alerts: [] },
        }));
    });

    it('muestra el número de alertas críticas pendientes de atender', async () => {
        vi.spyOn(axios, 'request').mockImplementation(async () => ({
            data: {
                alerts: [
                    alertFixture(),
                    alertFixture({ id: 'a1b2c3d4-0000-4000-8000-000000000002' }),
                    alertFixture({
                        id: 'a1b2c3d4-0000-4000-8000-000000000003',
                        severity: 'Advertencia',
                        status: 'Advertencia',
                    }),
                ],
            },
        }));

        renderAlertNotifier(client);

        await waitFor(() => {
            expect(screen.getByTestId('pending-alert-count')).toHaveTextContent('2');
        });
    });

    it('notifica visualmente la alerta crítica recibida por el canal de tiempo real', async () => {
        renderAlertNotifier(client);

        await waitFor(() => {
            expect(screen.getByTestId('pending-alert-count')).toHaveTextContent('0');
        });

        act(() => {
            client.emitEvent(REALTIME_EVENT.alertRaised, {
                alert_id: 'a1b2c3d4-0000-4000-8000-000000000009',
                batch_id: '6f1d0a9e-0d6a-4a9f-9d1a-0f3a2b7c4d51',
                alert_type: 'Anomalía Sphereon',
                severity: 'Crítica',
                message: 'Temperatura de formulación fuera de rango.',
                detected_at: '2026-01-05T09:35:00+00:00',
            });
        });

        const notification = await screen.findByRole('alert');
        expect(notification).toHaveTextContent('Anomalía Sphereon');
        expect(notification).toHaveTextContent('Temperatura de formulación fuera de rango.');

        await waitFor(() => {
            expect(screen.getByTestId('pending-alert-count')).toHaveTextContent('1');
        });
    });

    it('no computa como pendiente las alertas que no son críticas', async () => {
        renderAlertNotifier(client);

        await waitFor(() => {
            expect(screen.getByTestId('pending-alert-count')).toHaveTextContent('0');
        });

        act(() => {
            client.emitEvent(REALTIME_EVENT.alertRaised, {
                alert_id: 'a1b2c3d4-0000-4000-8000-000000000010',
                batch_id: '6f1d0a9e-0d6a-4a9f-9d1a-0f3a2b7c4d51',
                alert_type: 'Desviación térmica',
                severity: 'Advertencia',
                message: 'Desviación térmica leve.',
                detected_at: '2026-01-05T09:40:00+00:00',
            });
        });

        await waitFor(() => {
            expect(screen.getByTestId('pending-alert-count')).toHaveTextContent('0');
        });

        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });
});

describe('AlertHistoryTable', () => {
    it('muestra el tipo, el lote afectado, la fecha/hora y el estado de cada alerta', () => {
        render(<AlertHistoryTable alerts={[alertFixture()]} />);

        expect(screen.getByRole('columnheader', { name: 'Tipo' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Lote afectado' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Fecha/hora' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Estado' })).toBeInTheDocument();

        const row = screen.getByTestId('alert-history-row');
        expect(row).toHaveTextContent('Desviación térmica');
        expect(row).toHaveTextContent('6f1d0a9e-0d6a-4a9f-9d1a-0f3a2b7c4d51');
        expect(row).toHaveTextContent('2026-01-05T09:30:00+00:00');
        expect(row).toHaveTextContent('Crítica');
    });
});

describe('ExportTraceabilityReportButton', () => {
    const batchId = '6f1d0a9e-0d6a-4a9f-9d1a-0f3a2b7c4d51';

    it('descarga el reporte de trazabilidad del lote en el formato solicitado', async () => {
        const request = vi.spyOn(axios, 'request').mockImplementation(async () => ({
            data: new Blob(['contenido'], { type: 'text/csv' }),
        }));
        const createObjectURL = vi.fn(() => 'blob:trazabilidad');
        const revokeObjectURL = vi.fn();
        const originalCreateObjectURL = URL.createObjectURL;
        const originalRevokeObjectURL = URL.revokeObjectURL;
        Object.assign(URL, { createObjectURL, revokeObjectURL });
        const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined);

        try {
            render(<ExportTraceabilityReportButton batchId={batchId} format="csv" />);

            await userEvent.click(screen.getByTestId('export-traceability-report'));

            await waitFor(() => {
                expect(request).toHaveBeenCalledTimes(1);
            });

            const config = request.mock.calls[0][0] as { url: string; responseType?: string };
            expect(config.url).toContain(`/reports/traceability/${batchId}.csv`);
            expect(config.responseType).toBe('blob');
            expect(createObjectURL).toHaveBeenCalledTimes(1);
            expect(click).toHaveBeenCalledTimes(1);
            expect(revokeObjectURL).toHaveBeenCalledWith('blob:trazabilidad');
        } finally {
            Object.assign(URL, {
                createObjectURL: originalCreateObjectURL,
                revokeObjectURL: originalRevokeObjectURL,
            });
        }
    });
});
