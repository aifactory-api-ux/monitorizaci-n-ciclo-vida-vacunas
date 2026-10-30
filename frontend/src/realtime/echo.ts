import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

export const BATCH_MONITORING_CHANNEL = 'batch-monitoring';

/**
 * Nombres de los eventos de dominio difundidos por el canal de monitorización.
 *
 * Coinciden con los literales del catálogo de auditoría del backend, que es
 * la fuente de verdad del contrato de eventos (véase D11).
 */
export const REALTIME_EVENT = {
    batchCreated: 'batch.created',
    batchPhaseAdvanced: 'batch.phase.advanced',
    batchStatusChanged: 'batch.status.changed',
    physicalReceptionRegistered: 'batch.physical_reception.registered',
    logisticsCycleClosed: 'batch.logistics_cycle.closed',
    alertRaised: 'alert.raised',
} as const;

export type RealtimeEventName = (typeof REALTIME_EVENT)[keyof typeof REALTIME_EVENT];

/**
 * Todos los nombres de evento que el cliente reconoce en el canal.
 */
export const REALTIME_EVENT_NAMES: RealtimeEventName[] = Object.values(REALTIME_EVENT);

export interface RealtimeConfig {
    key: string;
    host: string;
    port: number;
    scheme: 'http' | 'https';
    activityTimeout: number;
    pongTimeout: number;
    unavailableTimeout: number;
}

const REQUIRED_VARIABLES = {
    key: 'VITE_REVERB_APP_KEY',
    host: 'VITE_REVERB_HOST',
} as const;

/**
 * Tiempos de espera del cliente WebSocket, en milisegundos.
 *
 * `activityTimeout` es el plazo sin actividad tras el cual el cliente da la
 * conexión por perdida, `pongTimeout` es la espera máxima del latido del
 * servidor y `unavailableTimeout` es el intervalo entre reintentos cuando el
 * servidor no está disponible. Son los parámetros que gobiernan la
 * reconexión automática tras un corte de red (véase D11).
 */
const DEFAULT_ACTIVITY_TIMEOUT = 30_000;
const DEFAULT_PONG_TIMEOUT = 10_000;
const DEFAULT_UNAVAILABLE_TIMEOUT = 10_000;

export class MissingRealtimeConfigurationError extends Error {
    public constructor(variable: string) {
        super(`Falta la variable de entorno ${variable} necesaria para la conexión de tiempo real`);
        this.name = 'MissingRealtimeConfigurationError';
    }
}

export function realtimeConfigFromEnv(env: Record<string, string | undefined>): RealtimeConfig {
    const missing = Object.values(REQUIRED_VARIABLES).filter((variable) => !env[variable]);

    if (missing.length > 0) {
        throw new MissingRealtimeConfigurationError(missing.join(', '));
    }

    const port = Number(env.VITE_REVERB_PORT ?? '8080');

    if (!Number.isInteger(port) || port <= 0) {
        throw new MissingRealtimeConfigurationError('VITE_REVERB_PORT');
    }

    const scheme = env.VITE_REVERB_SCHEME === 'http' ? 'http' : 'https';

    return {
        key: env.VITE_REVERB_APP_KEY as string,
        host: env.VITE_REVERB_HOST as string,
        port,
        scheme,
        activityTimeout: DEFAULT_ACTIVITY_TIMEOUT,
        pongTimeout: DEFAULT_PONG_TIMEOUT,
        unavailableTimeout: DEFAULT_UNAVAILABLE_TIMEOUT,
    };
}

/**
 * Carga útil de los eventos de dominio recibidos por el canal.
 */
export interface RealtimeEventPayload {
    batch_id?: string;
    from_phase?: string;
    to_phase?: string;
    current_campus_id?: string;
    phase?: string;
    status?: string;
    alert_id?: string;
    alert_type?: string;
    severity?: string;
    message?: string;
    detected_at?: string;
    [key: string]: unknown;
}

/**
 * Gestos de ciclo de vida que el cliente comunica a la aplicación.
 */
export interface RealtimeHandlers {
    onEvent(name: RealtimeEventName, payload: RealtimeEventPayload): void;
    onSubscribed(): void;
    onDisconnected(): void;
    onError(error: unknown): void;
}

/**
 * Cliente de tiempo real con el que trabaja la aplicación.
 *
 * Abstrae el transporte para que los hooks no dependan de `laravel-echo` ni
 * de `pusher-js`, y para que las pruebas puedan sustituirlo por un doble.
 */
export interface RealtimeClient {
    subscribe(channel: string, handlers: RealtimeHandlers): () => void;
    disconnect(): void;
}

/**
 * Suscripción de un canal al protocolo de difusión.
 */
interface EchoSubscription {
    bind(event: string, callback: (payload: unknown) => void): void;
    unbind(event: string, callback?: (payload: unknown) => void): void;
}

/**
 * Canal privado de `laravel-echo` con la suscripción de difusión expuesta.
 */
interface EchoChannel {
    subscription: EchoSubscription;
}

/**
 * Conexión del transporte WebSocket, que notifica los cambios de estado.
 */
interface EchoConnection {
    bind(event: string, callback: (payload: unknown) => void): void;
    unbind(event: string, callback?: (payload: unknown) => void): void;
}

const SUBSCRIPTION_SUCCEEDED_EVENT = 'pusher:subscription_succeeded';
const SUBSCRIPTION_ERROR_EVENT = 'pusher:subscription_error';
const STATE_CHANGE_EVENT = 'state_change';
const ERROR_EVENT = 'error';

function toPayload(payload: unknown): RealtimeEventPayload {
    if (typeof payload === 'object' && payload !== null) {
        return payload as RealtimeEventPayload;
    }

    return {};
}

/**
 * Opciones con las que se instancia el cliente de `laravel-echo`.
 */
export type RealtimeEchoOptions = {
    broadcaster: 'reverb';
    key: string;
    wsHost: string;
    wsPort: number;
    wssPort: number;
    forceTLS: boolean;
    enabledTransports: string[];
    activityTimeout: number;
    pongTimeout: number;
    unavailableTimeout: number;
    authEndpoint: string;
    bearerToken?: string;
    Pusher: typeof Pusher;
};

/**
 * Construye las opciones del cliente WebSocket.
 *
 * El token de acceso se envía como `Authorization: Bearer` en la petición de
 * autorización del canal privado, que el backend resuelve con el middleware
 * `auth:api` (véase D9 y D11).
 */
export function realtimeOptions(config: RealtimeConfig, token: string | null = null): RealtimeEchoOptions {
    return {
        broadcaster: 'reverb',
        key: config.key,
        wsHost: config.host,
        wsPort: config.port,
        wssPort: config.port,
        forceTLS: config.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        activityTimeout: config.activityTimeout,
        pongTimeout: config.pongTimeout,
        unavailableTimeout: config.unavailableTimeout,
        authEndpoint: '/broadcasting/auth',
        bearerToken: token ?? undefined,
        Pusher,
    };
}

/**
 * Crea el cliente de `laravel-echo` apuntando al servidor Reverb.
 */
export function createEcho(config: RealtimeConfig, token: string | null = null): Echo<'reverb'> {
    return new Echo(realtimeOptions(config, token));
}

/**
 * Envuelve un cliente de `laravel-echo` en la abstracción que usa la
 * aplicación.
 */
export function createRealtimeClient(echo: Echo<'reverb'>): RealtimeClient {
    return {
        subscribe(channel: string, handlers: RealtimeHandlers): () => void {
            const echoChannel = echo.private(channel) as unknown as EchoChannel;
            const subscription = echoChannel.subscription;
            const bindings: Array<[string, (payload: unknown) => void]> = [];
            let active = true;

            const bind = (event: string, callback: (payload: unknown) => void): void => {
                subscription.bind(event, callback);
                bindings.push([event, callback]);
            };

            REALTIME_EVENT_NAMES.forEach((name) => {
                bind(name, (payload) => {
                    if (!active) {
                        return;
                    }

                    handlers.onEvent(name, toPayload(payload));
                });
            });

            bind(SUBSCRIPTION_SUCCEEDED_EVENT, () => {
                if (active) {
                    handlers.onSubscribed();
                }
            });

            bind(SUBSCRIPTION_ERROR_EVENT, (status) => {
                if (active) {
                    handlers.onError(status);
                }
            });

            const connection = (echo.connector as unknown as { pusher?: { connection?: EchoConnection } })
                .pusher?.connection;

            const onStateChange = (payload: unknown): void => {
                if (!active) {
                    return;
                }

                const state = (payload as { current?: string } | null)?.current;

                if (state === 'disconnected' || state === 'unavailable') {
                    handlers.onDisconnected();
                }
            };

            const onConnectionError = (error: unknown): void => {
                if (active) {
                    handlers.onError(error);
                }
            };

            connection?.bind(STATE_CHANGE_EVENT, onStateChange);
            connection?.bind(ERROR_EVENT, onConnectionError);

            return () => {
                active = false;
                bindings.forEach(([event, callback]) => subscription.unbind(event, callback));
                connection?.unbind(STATE_CHANGE_EVENT, onStateChange);
                connection?.unbind(ERROR_EVENT, onConnectionError);
            };
        },

        disconnect(): void {
            echo.disconnect();
        },
    };
}

/**
 * Crea el cliente de tiempo real listo para suscribirse al canal de lotes.
 */
export function createBatchMonitoringClient(
    config: RealtimeConfig,
    token: string | null = null,
): RealtimeClient {
    return createRealtimeClient(createEcho(config, token));
}
