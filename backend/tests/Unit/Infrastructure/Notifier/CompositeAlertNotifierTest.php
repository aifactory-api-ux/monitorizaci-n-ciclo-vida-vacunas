<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Notifier;

use App\Domain\Alert\Service\AlertFactory;
use App\Domain\Shared\ValueObject\Uuid;
use App\Infrastructure\Notifier\CompositeAlertNotifier;
use Tests\Doubles\FakeAlertNotifier;
use Tests\TestCase;

/**
 * Notificador compuesto: entrega la alerta a todos los canales configurados.
 */
class CompositeAlertNotifierTest extends TestCase
{
    public function test_it_notifies_every_configured_channel(): void
    {
        $email = new FakeAlertNotifier('email');
        $sms = new FakeAlertNotifier('sms');

        $notifier = new CompositeAlertNotifier([$email, $sms]);

        $alert = (new AlertFactory)->thermalDeviation(Uuid::v4(), 4.5, 2.0);

        $notifier->notify($alert);

        $this->assertCount(2, $notifier->channels());
        $this->assertSame(1, $email->count());
        $this->assertSame(1, $sms->count());
        $this->assertSame($alert->id(), $email->notifications()[0]->id());
        $this->assertSame($alert->id(), $sms->notifications()[0]->id());
    }

    public function test_it_does_not_fail_without_configured_channels(): void
    {
        $notifier = new CompositeAlertNotifier([]);

        $notifier->notify((new AlertFactory)->thermalDeviation(Uuid::v4(), 4.5, 2.0));

        $this->assertSame([], $notifier->channels());
    }
}
