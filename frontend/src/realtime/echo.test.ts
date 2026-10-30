import { describe, expect, it, vi } from 'vitest';

import {
    MissingRealtimeConfigurationError,
    REALTIME_EVENT,
    createRealtimeClient,
    realtimeConfigFromEnv,
    realtimeOptions,
} from '@/realtime/echo';

type Listener = (payload: unknown) => void;

/**
 * Suscripción de difusión en memoria que reproduce la del protocolo de
 * `pusher-js`.
 */
class FakeSubscription {
    private listeners = new Map<string, Listener[]>();

    public bind(event: string, callback: Listener): void {
        this.listeners.set(event, [...(this.listeners.get(event) ?? []), callback]);
    }

    public unbind(event: string, callback?: Listener): void {
        if (!callback) {
            this.listeners.delete(event);

            return;
        }

        this.listeners.set(
            event,
            (this.listeners.get(event) ?? []).filter((listener) => listener !== callback),
        );
    }

    public emit(event: string, payload: unknown = {}): void {
        (this.listeners.get(event) ?? []).forEach((listener) => listener(payload));
    }

    public count(event: string): number {
        return (this.listeners.get(event) ?? []).length;
    }
}

/**
 * Cliente de `laravel-echo` en memoria, sin conexión de red.
 */
class FakeEcho {
    public subscription = new FakeSubscription();
    public connection = new FakeSubscription();
    public channelName: string | null = null;
    public disconnected = false;

    public connector = { pusher: { connection: this.connection } };

    public private(channel: string) {
        this.channelName = channel;

        return { subscription: this.subscription };
    }

    public disconnect(): void {
        this.disconnected = true;
    }
}

function fakeHandlers() {
    return {
        onEvent: vi.fn(),
        onSubscribed: vi.fn(),
        onDisconnected: vi.fn(),
        onError: vi.fn(),
    };
}

describe('configuración del cliente de tiempo real', () => {
    it('exige las variables de entorno del servidor Reverb', () => {
        expect(() => realtimeConfigFromEnv({})).toThrow(MissingRealtimeConfigurationError);
        expect(() => realtimeConfigFromEnv({ VITE_REVERB_APP_KEY: 'clave' })).toThrow(
            MissingRealtimeConfigurationError,
        );
    });

    it('resuelve el host, el puerto y los tiempos de reconexión', () => {
        const config = realtimeConfigFromEnv({
            VITE_REVERB_APP_KEY: 'clave',
            VITE_REVERB_HOST: 'reverb',
            VITE_REVERB_PORT: '8081',
            VITE_REVERB_SCHEME: 'http',
        });

        expect(config.host).toBe('reverb');
        expect(config.port).toBe(8081);
        expect(config.scheme).toBe('http');
        expect(config.pongTimeout).toBeLessThan(config.activityTimeout);
        expect(config.unavailableTimeout).toBeGreaterThan(0);
    });

    it('envía el token de acceso en la autorización del canal privado', () => {
        const config = realtimeConfigFromEnv({
            VITE_REVERB_APP_KEY: 'clave',
            VITE_REVERB_HOST: 'reverb',
        });

        const options = realtimeOptions(config, 'token-jwt');

        expect(options.bearerToken).toBe('token-jwt');
        expect(options.authEndpoint).toBe('/broadcasting/auth');
        expect(options.unavailableTimeout).toBe(config.unavailableTimeout);
    });
});

describe('suscripción al canal de monitorización', () => {
    it('recibe los eventos de dominio con su nombre exacto', () => {
        const echo = new FakeEcho();
        const client = createRealtimeClient(echo as never);
        const handlers = fakeHandlers();

        client.subscribe('batch-monitoring', handlers);

        expect(echo.channelName).toBe('batch-monitoring');

        echo.subscription.emit(REALTIME_EVENT.batchPhaseAdvanced, {
            batch_id: 'lote-1',
            from_phase: 'Fase de cultivo',
            to_phase: 'Formulación Sphereon (Campus 2)',
            current_campus_id: 'campus-2',
        });

        expect(handlers.onEvent).toHaveBeenCalledWith('batch.phase.advanced', {
            batch_id: 'lote-1',
            from_phase: 'Fase de cultivo',
            to_phase: 'Formulación Sphereon (Campus 2)',
            current_campus_id: 'campus-2',
        });
    });

    it('avisa de la resuscripción y de la pérdida de conexión', () => {
        const echo = new FakeEcho();
        const client = createRealtimeClient(echo as never);
        const handlers = fakeHandlers();

        client.subscribe('batch-monitoring', handlers);

        echo.subscription.emit('pusher:subscription_succeeded');
        expect(handlers.onSubscribed).toHaveBeenCalledTimes(1);

        echo.connection.emit('state_change', { previous: 'connected', current: 'disconnected' });
        expect(handlers.onDisconnected).toHaveBeenCalledTimes(1);

        echo.connection.emit('state_change', { previous: 'disconnected', current: 'unavailable' });
        expect(handlers.onDisconnected).toHaveBeenCalledTimes(2);
    });

    it('libera los escuchadores al cancelar la suscripción', () => {
        const echo = new FakeEcho();
        const client = createRealtimeClient(echo as never);
        const handlers = fakeHandlers();

        const unsubscribe = client.subscribe('batch-monitoring', handlers);
        unsubscribe();

        echo.subscription.emit(REALTIME_EVENT.alertRaised, { alert_id: 'alerta-1' });

        expect(handlers.onEvent).not.toHaveBeenCalled();
        expect(echo.subscription.count(REALTIME_EVENT.alertRaised)).toBe(0);
    });
});
