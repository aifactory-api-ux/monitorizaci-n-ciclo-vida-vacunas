import type {
    RealtimeClient,
    RealtimeEventName,
    RealtimeEventPayload,
    RealtimeHandlers,
} from '@/realtime/echo';

/**
 * Doble del cliente de tiempo real.
 *
 * Permite provocar los eventos y los cambios de estado de la conexión sin
 * abrir un WebSocket contra el servidor de Reverb.
 */
export class FakeRealtimeClient implements RealtimeClient {
    private handlers: RealtimeHandlers | null = null;

    public subscribe(_channel: string, handlers: RealtimeHandlers): () => void {
        this.handlers = handlers;

        return () => {
            this.handlers = null;
        };
    }

    public disconnect(): void {
        this.handlers = null;
    }

    public emitEvent(name: RealtimeEventName, payload: RealtimeEventPayload): void {
        this.handlers?.onEvent(name, payload);
    }

    public emitSubscribed(): void {
        this.handlers?.onSubscribed();
    }

    public emitDisconnected(): void {
        this.handlers?.onDisconnected();
    }
}
