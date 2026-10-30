<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifier;

use App\Domain\Alert\Entity\Alert;
use App\Domain\Alert\Port\AlertNotifier;

/**
 * Notificador compuesto de la alerta operativa.
 *
 * Entrega la alerta a todos los canales configurados: el correo electrónico,
 * que es la fuente de verdad, y el SMS, que es best-effort.
 */
final class CompositeAlertNotifier implements AlertNotifier
{
    /**
     * @param  list<AlertNotifier>  $notifiers  Canales de notificación configurados.
     */
    public function __construct(private readonly array $notifiers) {}

    public function notify(Alert $alert): void
    {
        foreach ($this->notifiers as $notifier) {
            $notifier->notify($alert);
        }
    }

    /**
     * Canales de notificación configurados.
     *
     * @return list<AlertNotifier>
     */
    public function channels(): array
    {
        return $this->notifiers;
    }
}
