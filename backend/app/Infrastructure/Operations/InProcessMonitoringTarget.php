<?php

declare(strict_types=1);

namespace App\Infrastructure\Operations;

use App\Domain\Operations\Port\MonitoringTarget;

/**
 * Objetivo de monitorización que mide el tiempo en el propio proceso.
 *
 * Mide el tiempo transcurrido entre el inicio y el final de la operación,
 * que es la latencia observada por el servidor de aplicaciones (véase D21).
 */
final class InProcessMonitoringTarget implements MonitoringTarget
{
    public function measure(callable $operation): float
    {
        $startedAt = microtime(true);

        $operation();

        return microtime(true) - $startedAt;
    }
}
