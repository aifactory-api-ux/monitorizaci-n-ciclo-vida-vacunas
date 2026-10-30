<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Alert;

use App\Domain\Alert\Service\AlertFactory;
use App\Domain\Alert\ValueObject\AlertSeverity;
use App\Domain\Alert\ValueObject\AlertType;
use App\Domain\Shared\ValueObject\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Generación de las alertas operativas de prioridad máxima (véase TC005 y
 * TC023).
 */
class AlertFactoryTest extends TestCase
{
    private AlertFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new AlertFactory;
    }

    public function test_it_creates_a_critical_thermal_deviation_alert(): void
    {
        $batchId = Uuid::v4();
        $detectedAt = new DateTimeImmutable('2026-01-05T09:30:00+00:00');

        $alert = $this->factory->thermalDeviation($batchId, 4.5, 2.0, $detectedAt);

        $this->assertSame($batchId, $alert->batchId());
        $this->assertSame(AlertType::THERMAL_DEVIATION, $alert->alertType()->value());
        $this->assertSame(AlertSeverity::CRITICAL, $alert->severity()->value());
        $this->assertTrue($alert->isCritical());
        $this->assertSame(
            'Desviación térmica de 4.50 °C sobre el umbral permitido de 2.00 °C.',
            $alert->message(),
        );
        $this->assertEquals($detectedAt, $alert->createdAt());
        $this->assertTrue(Uuid::isValid($alert->id()));
    }

    public function test_it_creates_a_critical_sphereon_anomaly_alert(): void
    {
        $batchId = Uuid::v4();
        $detectedAt = new DateTimeImmutable('2026-01-05T11:45:00+00:00');

        $alert = $this->factory->sphereonAnomaly($batchId, 7.25, 5.0, $detectedAt);

        $this->assertSame($batchId, $alert->batchId());
        $this->assertSame(AlertType::SPHEREON_ANOMALY, $alert->alertType()->value());
        $this->assertSame(AlertSeverity::CRITICAL, $alert->severity()->value());
        $this->assertTrue($alert->isCritical());
        $this->assertSame(
            'Anomalía de formulación Sphereon de 7.25 puntos porcentuales sobre el umbral permitido de 5.00.',
            $alert->message(),
        );
        $this->assertEquals($detectedAt, $alert->createdAt());
        $this->assertTrue(Uuid::isValid($alert->id()));
    }

    public function test_it_registers_the_detection_date_when_it_is_not_given(): void
    {
        $before = new DateTimeImmutable;

        $alert = $this->factory->thermalDeviation(Uuid::v4(), 4.5, 2.0);

        $this->assertGreaterThanOrEqual($before, $alert->createdAt());
    }

    public function test_it_creates_an_alert_of_priority_other_than_the_maximum_one(): void
    {
        $alert = $this->factory->create(
            Uuid::v4(),
            AlertType::from(AlertType::THERMAL_DEVIATION),
            AlertSeverity::from(AlertSeverity::WARNING),
            'Lectura térmica dentro del rango admitido.',
        );

        $this->assertSame(AlertSeverity::WARNING, $alert->severity()->value());
        $this->assertFalse($alert->isCritical());
    }
}
