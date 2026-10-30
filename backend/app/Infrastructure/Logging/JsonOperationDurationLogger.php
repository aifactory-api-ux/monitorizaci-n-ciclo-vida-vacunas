<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use App\Domain\Audit\Port\OperationDurationLogger;
use Psr\Log\LoggerInterface;

/**
 * Emisor de la duración de las operaciones en JSON estructurado.
 *
 * Publica la duración de la consulta en el canal de logs, de modo que la
 * latencia de la búsqueda sea medible por el agregador sin consultar la
 * base de datos (véase D21).
 */
final class JsonOperationDurationLogger implements OperationDurationLogger
{
    public function __construct(private readonly LoggerInterface $logger) {}

    /**
     * Registra la duración de una operación.
     *
     * @param  array<string, mixed>  $context
     */
    public function record(string $operation, float $durationMilliseconds, array $context = []): void
    {
        $this->logger->info('operation.duration', [
            'operation' => $operation,
            'duration_ms' => round($durationMilliseconds, 3),
            'context' => $context,
        ]);
    }
}
