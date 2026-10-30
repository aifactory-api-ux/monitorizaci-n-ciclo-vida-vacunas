<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifier;

use App\Domain\Alert\Entity\Alert;
use App\Domain\Alert\Port\AlertNotifier;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Message;

/**
 * Canal de notificación por correo electrónico.
 *
 * Es el canal fiable de la organización: la alerta crítica se entrega al
 * Supervisor de Planta con el tipo, el lote afectado, la severidad, el
 * mensaje y la fecha y hora de la detección.
 */
final class EmailAlertNotifier implements AlertNotifier
{
    /**
     * @param  list<string>  $recipients  Destinatarios configurados para el canal.
     */
    public function __construct(
        private readonly Mailer $mailer,
        private readonly array $recipients,
    ) {}

    public function notify(Alert $alert): void
    {
        $this->mailer->raw(
            $this->body($alert),
            function (Message $message) use ($alert): void {
                $message->to($this->recipients)->subject($this->subject($alert));
            },
        );
    }

    /**
     * Asunto del mensaje de alerta.
     */
    private function subject(Alert $alert): string
    {
        return sprintf(
            '[Alerta crítica] %s en el lote %s',
            $alert->alertType()->value(),
            $alert->batchId(),
        );
    }

    /**
     * Cuerpo del mensaje de alerta.
     */
    private function body(Alert $alert): string
    {
        return implode(PHP_EOL, [
            sprintf('Alerta de prioridad máxima: %s', $alert->alertType()->value()),
            sprintf('Lote afectado: %s', $alert->batchId()),
            sprintf('Severidad: %s', $alert->severity()->value()),
            sprintf('Mensaje: %s', $alert->message()),
            sprintf('Detectada: %s', $alert->createdAt()->format(DATE_ATOM)),
        ]);
    }
}
