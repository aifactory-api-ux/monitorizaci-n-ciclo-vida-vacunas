<?php

declare(strict_types=1);

namespace App\Domain\Shared\Event;

/**
 * Evento de dominio publicado en los canales de tiempo real.
 *
 * El evento declara su nombre en el cable y su carga útil. No depende de
 * ninguna infraestructura, de modo que el dominio puede construirse y
 * verificarse sin broadcasting, Redis ni WebSockets (véase D11).
 */
interface DomainEvent
{
    /**
     * Nombre del evento tal y como lo recibe el suscriptor del canal.
     */
    public function name(): string;

    /**
     * Carga útil del evento.
     *
     * @return array<string, mixed>
     */
    public function payload(): array;
}
