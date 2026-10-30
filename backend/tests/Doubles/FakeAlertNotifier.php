<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Domain\Alert\Entity\Alert;
use App\Domain\Alert\Port\AlertNotifier;

/**
 * Doble en memoria de un canal de notificación de alertas.
 *
 * Sustituye a los adaptadores de correo electrónico y de SMS durante las
 * pruebas: registra las alertas notificadas para comprobar que la alerta
 * crítica se entrega a los canales configurados sin intervención manual.
 */
final class FakeAlertNotifier implements AlertNotifier
{
    /**
     * Alertas notificadas por el canal.
     *
     * @var list<Alert>
     */
    private array $notifications = [];

    /**
     * @param  string  $channel  Nombre del canal que representa el doble.
     */
    public function __construct(private readonly string $channel = 'doble') {}

    public function notify(Alert $alert): void
    {
        $this->notifications[] = $alert;
    }

    /**
     * Nombre del canal que representa el doble.
     */
    public function channel(): string
    {
        return $this->channel;
    }

    /**
     * Alertas notificadas por el canal.
     *
     * @return list<Alert>
     */
    public function notifications(): array
    {
        return $this->notifications;
    }

    /**
     * Número de alertas notificadas por el canal.
     */
    public function count(): int
    {
        return count($this->notifications);
    }
}
