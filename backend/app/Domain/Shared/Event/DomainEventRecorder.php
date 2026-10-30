<?php

declare(strict_types=1);

namespace App\Domain\Shared\Event;

/**
 * Puerto de registro de eventos de dominio.
 *
 * Los casos de uso dependen de este puerto y no del adaptador concreto: el
 * dominio anuncia un hecho consumado y el adaptador decide cómo se difunde
 * (véase D11).
 */
interface DomainEventRecorder
{
    /**
     * Registra el evento para su difusión en los canales de tiempo real.
     */
    public function record(DomainEvent $event): void;
}
