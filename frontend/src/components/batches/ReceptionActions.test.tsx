import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axios, { AxiosError, type AxiosResponse } from 'axios';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { ReceptionActions } from '@/components/batches/ReceptionActions';
import type { Batch } from '@/types/batch';

const BATCH_ID = '6f1d0a9e-0d6a-4a9f-9d1a-0f3a2b7c4d51';

/**
 * Error de API con la respuesta que publica el backend.
 */
function apiError(status: number, message: string, errorCode: string): AxiosError {
    const response = {
        data: { message, error_code: errorCode },
        status,
        statusText: 'Error',
        headers: {},
        config: { headers: new axios.AxiosHeaders() },
    } as unknown as AxiosResponse;

    return new AxiosError('Request failed', AxiosError.ERR_BAD_REQUEST, undefined, undefined, response);
}

function batchFixture(overrides: Partial<Batch> = {}): Batch {
    return {
        id: BATCH_ID,
        reference_id: 'LOTE-2026-0001',
        phase: 'Fase logística (Campus 3)',
        status: 'En curso',
        current_campus_id: 'campus-3',
        created_at: '2026-01-05T08:00:00+00:00',
        updated_at: '2026-01-05T08:00:00+00:00',
        ...overrides,
    };
}

function renderReceptionActions(batch: Batch) {
    const queryClient = new QueryClient({
        defaultOptions: { queries: { retry: false } },
    });

    return render(
        <QueryClientProvider client={queryClient}>
            <ReceptionActions batch={batch} />
        </QueryClientProvider>,
    );
}

describe('ReceptionActions', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
    });

    it('ofrece el registro de recepción mientras la API no publica la recepción', () => {
        renderReceptionActions(batchFixture());

        expect(screen.getByTestId('register-reception-button')).toBeEnabled();
        expect(screen.queryByTestId('close-logistics-cycle-button')).not.toBeInTheDocument();
    });

    it('ofrece el cierre del ciclo logístico cuando la API publica la recepción', () => {
        renderReceptionActions(batchFixture({ status: 'Recibido' }));

        expect(screen.getByTestId('reception-actions-received')).toHaveTextContent(
            'La recepción física del lote ya está registrada.',
        );
        expect(screen.getByTestId('close-logistics-cycle-button')).toBeEnabled();
        expect(screen.queryByTestId('register-reception-button')).not.toBeInTheDocument();
    });

    it('no ofrece acciones cuando la API publica el ciclo logístico cerrado', () => {
        renderReceptionActions(batchFixture({ status: 'Cerrado' }));

        expect(screen.getByTestId('reception-actions-closed')).toHaveTextContent(
            'El ciclo logístico del lote está cerrado.',
        );
        expect(screen.queryByTestId('register-reception-button')).not.toBeInTheDocument();
        expect(screen.queryByTestId('close-logistics-cycle-button')).not.toBeInTheDocument();
    });

    it('solicita a la API el registro de la recepción física del lote', async () => {
        const request = vi.spyOn(axios, 'request').mockImplementation(async () => ({
            data: batchFixture({ status: 'Recibido' }),
        }));

        renderReceptionActions(batchFixture());

        await userEvent.click(screen.getByTestId('register-reception-button'));

        await waitFor(() => {
            expect(request).toHaveBeenCalledWith(
                expect.objectContaining({
                    url: `/api/v1/batches/${BATCH_ID}/reception`,
                    method: 'POST',
                }),
            );
        });
    });

    it('solicita a la API el cierre del ciclo logístico del lote', async () => {
        const request = vi.spyOn(axios, 'request').mockImplementation(async () => ({
            data: batchFixture({ status: 'Cerrado' }),
        }));

        renderReceptionActions(batchFixture({ status: 'Recibido' }));

        await userEvent.click(screen.getByTestId('close-logistics-cycle-button'));

        await waitFor(() => {
            expect(request).toHaveBeenCalledWith(
                expect.objectContaining({
                    url: `/api/v1/batches/${BATCH_ID}/logistics-close`,
                    method: 'POST',
                }),
            );
        });
    });

    it('muestra el mensaje publicado por la API cuando la operación no está permitida', async () => {
        vi.spyOn(axios, 'request').mockRejectedValue(
            apiError(
                422,
                'La recepción física sólo puede registrarse sobre un lote en fase logística.',
                'invalid_reception_phase',
            ),
        );

        renderReceptionActions(batchFixture());

        await userEvent.click(screen.getByTestId('register-reception-button'));

        expect(await screen.findByTestId('reception-actions-error')).toHaveTextContent(
            'La recepción física sólo puede registrarse sobre un lote en fase logística.',
        );
    });
});
