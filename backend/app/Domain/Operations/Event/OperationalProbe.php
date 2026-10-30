<?php

declare(strict_types=1);

namespace App\Domain\Operations\Event;

use App\Domain\Shared\Event\DomainEvent;

/**
 * Sonda operativa de los umbrales no funcionales.
 *
 * El comando de verificación publica la sonda en el canal privado de
 * monitorización: su publicación mide la latencia de la ruta de publicación
 * de eventos y, a la vez, deja constancia del resultado de la medición. El
 * panel ignora el evento porque no forma parte de los hechos del ciclo de
 * vida del lote (véase NFR006).
 */
final class OperationalProbe implements DomainEvent
{
    /**
     * Nombre del evento en el cable.
     */
    public const EVENT_NAME = 'monitoring.probe';

    /**
     * @param  array<string, mixed>  $measurements
     */
    public function __construct(private readonly array $measurements) {}

    /**
     * Nombre del evento en el cable.
     */
    public function name(): string
    {
        return self::EVENT_NAME;
    }

    /**
     * Carga útil del evento.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'measurements' => $this->measurements,
        ];
    }
}
