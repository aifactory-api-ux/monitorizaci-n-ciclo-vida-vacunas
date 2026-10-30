<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Alert;

use App\Domain\Alert\Service\AlertFactory;
use App\Domain\Alert\Service\SphereonAnomalyDetector;
use App\Domain\Alert\ValueObject\AlertSeverity;
use App\Domain\Alert\ValueObject\AlertType;
use App\Domain\Shared\ValueObject\Uuid;
use Tests\TestCase;

/**
 * Detección de la anomalía de formulación Sphereon con el umbral de la
 * configuración de monitorización (véase TC023).
 */
class SphereonAnomalyDetectorTest extends TestCase
{
    public function test_it_raises_an_alert_when_the_anomaly_exceeds_the_threshold(): void
    {
        $threshold = (float) config('monitoring.sphereon.anomaly_threshold');
        $detector = new SphereonAnomalyDetector(new AlertFactory, $threshold);

        $alert = $detector->detect(Uuid::v4(), $threshold + 1.5);

        $this->assertNotNull($alert);
        $this->assertSame(AlertType::SPHEREON_ANOMALY, $alert->alertType()->value());
        $this->assertSame(AlertSeverity::CRITICAL, $alert->severity()->value());
    }

    public function test_it_raises_an_alert_when_the_anomaly_is_below_the_threshold(): void
    {
        $threshold = (float) config('monitoring.sphereon.anomaly_threshold');
        $detector = new SphereonAnomalyDetector(new AlertFactory, $threshold);

        $this->assertNotNull($detector->detect(Uuid::v4(), -($threshold + 1.5)));
    }

    public function test_it_does_not_raise_an_alert_when_the_reading_is_within_the_threshold(): void
    {
        $threshold = (float) config('monitoring.sphereon.anomaly_threshold');
        $detector = new SphereonAnomalyDetector(new AlertFactory, $threshold);

        $this->assertNull($detector->detect(Uuid::v4(), 0.0));
        $this->assertNull($detector->detect(Uuid::v4(), $threshold / 2));
        $this->assertNull($detector->detect(Uuid::v4(), $threshold));
    }

    public function test_it_uses_the_threshold_declared_in_the_monitoring_configuration(): void
    {
        config(['monitoring.sphereon.anomaly_threshold' => 1.5]);

        $detector = $this->app->make(SphereonAnomalyDetector::class);

        $this->assertSame(1.5, $detector->threshold());
        $this->assertNull($detector->detect(Uuid::v4(), 1.4));
        $this->assertNotNull($detector->detect(Uuid::v4(), 1.6));
    }
}
