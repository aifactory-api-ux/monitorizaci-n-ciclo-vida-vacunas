import { useQuery } from '@tanstack/react-query';

import { apiRequest } from '@/api/httpClient';

interface HealthResponse {
    status: 'ok' | 'degraded';
    service: string;
    environment: string;
    timestamp: string;
    checks: Record<string, { status: string }>;
}

export function useHealthStatus() {
    return useQuery({
        queryKey: ['health'],
        queryFn: () => apiRequest<HealthResponse>('/health'),
        retry: 0,
    });
}
