import axios, { AxiosError } from 'axios';

const API_BASE_URL_VARIABLE = 'VITE_API_BASE_URL';

export class MissingConfigurationError extends Error {
    public constructor(variable: string) {
        super(`Falta la variable de entorno ${variable} necesaria para hablar con la API`);
        this.name = 'MissingConfigurationError';
    }
}

export class ApiError extends Error {
    public readonly status: number;
    public readonly errorCode: string | null;

    public constructor(status: number, message: string, errorCode: string | null = null) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errorCode = errorCode;
    }
}

/**
 * Extrae el mensaje y el código de error que publica la API.
 */
function apiErrorPayload(data: unknown): { message?: string; errorCode?: string } {
    if (typeof data !== 'object' || data === null) {
        return {};
    }

    const payload = data as { message?: unknown; error_code?: unknown };

    return {
        message: typeof payload.message === 'string' ? payload.message : undefined,
        errorCode: typeof payload.error_code === 'string' ? payload.error_code : undefined,
    };
}

export function apiBaseUrl(): string {
    const baseUrl = import.meta.env[API_BASE_URL_VARIABLE];

    if (!baseUrl) {
        throw new MissingConfigurationError(API_BASE_URL_VARIABLE);
    }

    return baseUrl.replace(/\/+$/, '');
}

export interface ApiRequestOptions {
    method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
    body?: unknown;
    token?: string | null;
    signal?: AbortSignal;
    query?: Record<string, string | number | boolean | null | undefined>;
    responseType?: 'json' | 'blob';
}

function buildQueryString(query: Record<string, string | number | boolean | null | undefined>): string {
    const params = new URLSearchParams();

    Object.entries(query).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '') {
            return;
        }

        params.append(key, String(value));
    });

    const queryString = params.toString();

    return queryString === '' ? '' : `?${queryString}`;
}

export async function apiRequest<T>(path: string, options: ApiRequestOptions = {}): Promise<T> {
    const { method = 'GET', body, token, signal, query, responseType = 'json' } = options;

    const headers: Record<string, string> = {
        Accept: 'application/json',
    };

    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    try {
        const response = await axios.request<T>({
            url: `${apiBaseUrl()}${path}${buildQueryString(query ?? {})}`,
            method,
            headers,
            signal,
            data: body,
            responseType,
        });

        return response.data;
    } catch (error) {
        if (error instanceof AxiosError) {
            const status = error.response?.status ?? 0;
            const payload = apiErrorPayload(error.response?.data);

            throw new ApiError(
                status,
                payload.message ?? `La API respondió ${status} para ${method} ${path}`,
                payload.errorCode ?? null,
            );
        }

        throw error;
    }
}
