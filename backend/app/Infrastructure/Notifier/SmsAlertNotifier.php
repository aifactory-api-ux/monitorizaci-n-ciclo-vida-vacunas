<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifier;

use App\Domain\Alert\Entity\Alert;
use App\Domain\Alert\Port\AlertNotifier;
use Psr\Log\LoggerInterface;

/**
 * Canal de notificación por SMS.
 *
 * El proveedor de SMS On-Premise está pendiente de confirmación, de modo que
 * este canal es best-effort: deja constancia de la notificación enviada a los
 * destinatarios configurados y no sustituye al canal de correo electrónico,
 * que es la fuente de verdad de la alerta (véase D1).
 */
final class SmsAlertNotifier implements AlertNotifier
{
    /**
     * @param  list<string>  $recipients  Destinatarios configurados para el canal.
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly array $recipients,
    ) {}

    public function notify(Alert $alert): void
    {
        $this->logger->warning('Alerta crítica notificada por SMS a los destinatarios configurados.', [
            'alert_id' => $alert->id(),
            'batch_id' => $alert->batchId(),
            'alert_type' => $alert->alertType()->value(),
            'severity' => $alert->severity()->value(),
            'message' => $alert->message(),
            'recipients' => $this->recipients,
            'detected_at' => $alert->createdAt()->format(DATE_ATOM),
        ]);
    }
}
