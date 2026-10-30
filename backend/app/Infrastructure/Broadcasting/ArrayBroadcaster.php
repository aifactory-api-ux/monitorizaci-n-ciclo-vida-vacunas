<?php

declare(strict_types=1);

namespace App\Infrastructure\Broadcasting;

use App\Domain\Realtime\Port\RealtimeSubscriberRegistry;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\Broadcasters\UsePusherChannelConventions;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Emisor de eventos en memoria.
 *
 * Es el driver `array` de la aplicación: guarda las difusiones en el propio
 * proceso en lugar de publicarlas en Reverb o Redis, de modo que el entorno de
 * pruebas no abre ninguna conexión externa. La autorización de canales aplica
 * la misma matriz que el resto de la aplicación: un canal privado exige un
 * usuario autenticado y la autorización registrada para ese canal.
 *
 * Las difusiones se reparten además entre los suscriptores conectados al
 * canal, de modo que la capacidad del canal y la latencia de entrega sean
 * medibles sin levantar el servidor de WebSockets (véase D11 y NFR009).
 */
class ArrayBroadcaster extends Broadcaster
{
    use UsePusherChannelConventions;

    /**
     * @param  RealtimeSubscriberRegistry|null  $subscribers  Suscriptores conectados al canal.
     */
    public function __construct(private readonly ?RealtimeSubscriberRegistry $subscribers = null) {}

    /**
     * Difusiones registradas, con sus canales y su carga útil.
     *
     * Cada difusión conserva el instante en que se entrega, de modo que las
     * pruebas puedan medir la latencia entre el origen del evento y su
     * entrega por el canal de tiempo real (véase D11).
     *
     * @var list<array{channels: list<string>, event: string, payload: array<string, mixed>, delivered_at: float}>
     */
    private array $broadcasts = [];

    /**
     * Autoriza la petición de suscripción a un canal.
     *
     * @throws AccessDeniedHttpException Si el canal es privado y no hay un usuario
     *                                   autenticado, o si el usuario no está autorizado.
     */
    public function auth($request): mixed
    {
        $channelName = $this->normalizeChannelName((string) $request->channel_name);

        if ($channelName === '') {
            throw new AccessDeniedHttpException;
        }

        if ($this->isGuardedChannel((string) $request->channel_name)
            && ! $this->retrieveUser($request, $channelName)) {
            throw new AccessDeniedHttpException;
        }

        return $this->verifyUserCanAccessChannel($request, $channelName);
    }

    /**
     * Valida la respuesta de autorización del canal.
     *
     * @param  mixed  $result
     */
    public function validAuthenticationResponse($request, $result): mixed
    {
        if (is_bool($result)) {
            return json_encode($result);
        }

        $channelName = $this->normalizeChannelName((string) $request->channel_name);

        $user = $this->retrieveUser($request, $channelName);

        if ($user === null) {
            return json_encode($result);
        }

        $broadcastIdentifier = method_exists($user, 'getAuthIdentifierForBroadcasting')
            ? $user->getAuthIdentifierForBroadcasting()
            : $user->getAuthIdentifier();

        return json_encode(['channel_data' => [
            'user_id' => $broadcastIdentifier,
            'user_info' => $result,
        ]]);
    }

    /**
     * Registra la difusión de un evento en memoria.
     *
     * @param  array<int, string>  $channels
     * @param  array<string, mixed>  $payload
     */
    public function broadcast(array $channels, $event, array $payload = []): void
    {
        $formatted = $this->formatChannels($channels);

        $this->broadcasts[] = [
            'channels' => $formatted,
            'event' => $event,
            'payload' => $payload,
            'delivered_at' => microtime(true),
        ];

        if ($this->subscribers === null) {
            return;
        }

        foreach ($formatted as $channel) {
            $this->subscribers->deliver($this->normalizeChannelName($channel), $event, $payload);
        }
    }

    /**
     * Difusiones registradas desde el arranque del proceso.
     *
     * @return list<array{channels: list<string>, event: string, payload: array<string, mixed>, delivered_at: float}>
     */
    public function broadcasts(): array
    {
        return $this->broadcasts;
    }

    /**
     * Vacía el registro de difusiones.
     */
    public function flush(): void
    {
        $this->broadcasts = [];
    }
}
