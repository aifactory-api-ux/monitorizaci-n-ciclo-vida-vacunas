<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Notifier;

use App\Domain\Alert\Service\AlertFactory;
use App\Domain\Alert\ValueObject\AlertType;
use App\Domain\Shared\ValueObject\Uuid;
use App\Infrastructure\Notifier\EmailAlertNotifier;
use DateTimeImmutable;
use Illuminate\Contracts\Mail\Mailer;
use Tests\TestCase;

/**
 * Canal de notificación por correo electrónico de las alertas de prioridad
 * máxima (véase TC019).
 */
class EmailAlertNotifierTest extends TestCase
{
    public function test_it_sends_the_alert_to_the_configured_recipients(): void
    {
        $recipients = ['supervisor.planta@vaccines.local'];
        $notifier = new EmailAlertNotifier($this->app->make(Mailer::class), $recipients);

        $batchId = Uuid::v4();
        $alert = (new AlertFactory)->thermalDeviation(
            $batchId,
            4.5,
            2.0,
            new DateTimeImmutable('2026-01-05T09:30:00+00:00'),
        );

        $notifier->notify($alert);

        $transport = $this->app->make(Mailer::class)->getSymfonyTransport();

        $this->assertTrue(method_exists($transport, 'messages'));

        $messages = $transport->messages();

        $this->assertCount(1, $messages);

        $message = $messages->first();
        $email = $message->getOriginalMessage();

        $this->assertSame($recipients, array_map(
            fn ($address): string => $address->getAddress(),
            $message->getEnvelope()->getRecipients(),
        ));

        $this->assertStringContainsString('[Alerta crítica]', (string) $email->getSubject());
        $this->assertStringContainsString(AlertType::THERMAL_DEVIATION, (string) $email->getSubject());
        $this->assertStringContainsString($batchId, (string) $email->getSubject());

        $body = (string) $email->getTextBody();

        $this->assertStringContainsString('Severidad: Crítica', $body);
        $this->assertStringContainsString($alert->message(), $body);
        $this->assertStringContainsString('2026-01-05T09:30:00+00:00', $body);
    }
}
