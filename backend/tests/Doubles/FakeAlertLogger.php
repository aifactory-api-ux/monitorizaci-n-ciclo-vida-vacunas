<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Psr\Log\LoggerInterface;
use Stringable;

/**
 * Doble en memoria de un registrador de avisos.
 *
 * Sustituye al registrador de la aplicación durante las pruebas: guarda los
 * registros emitidos para comprobar que el canal de notificación deja
 * constancia de la alerta enviada.
 */
final class FakeAlertLogger implements LoggerInterface
{
    /**
     * Registros emitidos.
     *
     * @var list<array{level: string, message: string, context: array<string, mixed>}>
     */
    private array $records = [];

    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->record('emergency', $message, $context);
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->record('alert', $message, $context);
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->record('critical', $message, $context);
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $this->record('error', $message, $context);
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->record('warning', $message, $context);
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->record('notice', $message, $context);
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $this->record('info', $message, $context);
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->record('debug', $message, $context);
    }

    /**
     * @param  mixed  $level
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->record((string) $level, $message, $context);
    }

    /**
     * Registros emitidos.
     *
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public function records(): array
    {
        return $this->records;
    }

    /**
     * Registros de nivel de aviso.
     *
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public function warnings(): array
    {
        return array_values(array_filter(
            $this->records,
            fn (array $record): bool => $record['level'] === 'warning',
        ));
    }

    /**
     * Registra una entrada del registrador.
     *
     * @param  array<string, mixed>  $context
     */
    private function record(string $level, string|Stringable $message, array $context): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
