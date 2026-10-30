<?php

declare(strict_types=1);

namespace App\Domain\Operations\Port;

/**
 * Objetivo de monitorización que mide el tiempo de una operación.
 *
 * La medición del tiempo transcurrido pertenece a la infraestructura, de
 * modo que el dominio sólo conoce el resultado de la medición (véase D21).
 */
interface MonitoringTarget
{
    /**
     * Ejecuta la operación indicada y devuelve el tiempo transcurrido, en
     * segundos.
     *
     * @param  callable(): mixed  $operation
     */
    public function measure(callable $operation): float;
}
