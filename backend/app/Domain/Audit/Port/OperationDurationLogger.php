<?php

declare(strict_types=1);

namespace App\Domain\Audit\Port;

/**
 * Puerto de registro de la duración de una operación.
 *
 * Las duraciones se publican en la salida estructurada de la aplicación para
 * que los umbrales de latencia del despliegue sean medibles sin consultar la
 * base de datos (véase D21).
 */
interface OperationDurationLogger
{
    /**
     * Registra la duración de una operación.
     *
     * @param  array<string, mixed>  $context
     */
    public function record(string $operation, float $durationMilliseconds, array $context = []): void;
}
