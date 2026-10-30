import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import axios from 'axios';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { HealthPanel } from '@/components/health/HealthPanel';

function renderHealthPanel() {
    const queryClient = new QueryClient({
        defaultOptions: { queries: { retry: false } },
    });

    return render(
        <QueryClientProvider client={queryClient}>
            <HealthPanel />
        </QueryClientProvider>,
    );
}

describe('HealthPanel', () => {
    beforeEach(() => {
        vi.spyOn(axios, 'request').mockResolvedValue({
            data: {
                status: 'ok',
                service: 'Vaccine Batch Tracking',
                environment: 'testing',
                timestamp: '2026-01-01T00:00:00Z',
                checks: { database: { status: 'ok', driver: 'sqlite' } },
            },
        });
    });

    it('muestra el estado de cada dependencia del servicio', async () => {
        renderHealthPanel();

        expect(await screen.findByText('database')).toBeInTheDocument();
        expect(screen.getByText('ok')).toBeInTheDocument();
    });

    it('avisa cuando no se puede consultar el estado del servicio', async () => {
        vi.spyOn(axios, 'request').mockRejectedValue(new Error('sin red'));

        renderHealthPanel();

        expect(await screen.findByRole('alert')).toHaveTextContent('No se ha podido consultar el estado del servicio');
    });
});
