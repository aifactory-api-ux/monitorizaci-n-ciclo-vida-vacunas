<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Notifier;

use App\Domain\Alert\Service\AlertFactory;
use App\Domain\Alert\ValueObject\AlertType;
use App\Domain\Shared\ValueObject\Uuid;
use App\Infrastructure\Notifier\SmsAlertNotifier;
use DateTimeImmutable;
use Tests\Doubles\FakeAlertLogger;
use Tests\TestCase;

/**
 * Canal de notificación por SMS de las alertas de prioridad máxima: es
 * best-effort y deja constancia de la alerta enviada a los destinatarios
 * configurados (véase D1).
 */
class SmsAlertNotifierTest extends TestCase
{
    public function test_it_records_the_notification_sent_to_the_configured_recipients(): void
    {
        $logger = new FakeAlertLogger;
        $notifier = new SmsAlertNotifier($logger, ['+34600000000']);

        $batchId = Uuid::v4();
        $alert = (new AlertFactory)->sphereonAnomaly(
            $batchId,
            7.5,
            5.0,
            new DateTimeImmutable('2026-01-05T11:45:00+00:00'),
        );

        $notifier->notify($alert);

        $warnings = $logger->warnings();

        $this->assertCount(1, $warnings);
        $this->assertSame(AlertType::SPHEREON_ANOMALY, $warnings[0]['context']['alert_type']);
        $this->assertSame($batchId, $warnings[0]['context']['batch_id']);
        $this->assertSame('Crítica', $warnings[0]['context']['severity']);
        $this->assertSame(['+34600000000'], $warnings[0]['context']['recipients']);
        $this->assertSame($alert->message(), $warnings[0]['context']['message']);
        $this->assertSame('2026-01-05T11:45:00+00:00', $warnings[0]['context']['detected_at']);
    }
}
