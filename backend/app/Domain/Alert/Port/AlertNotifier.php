<?php

declare(strict_types=1);

namespace App\Domain\Alert\Port;

use App\Domain\Alert\Entity\Alert;

/**
 * Puerto de notificación de las alertas operativas de prioridad máxima.
 *
 * Los adaptadores viven en la capa de infraestructura: el canal de correo
 * electrónico, el canal SMS, de carácter best-effort, y el notificador
 * compuesto que entrega la alerta a todos los canales configurados.
 */
interface AlertNotifier
{
    /**
     * Notifica la alerta por su canal.
     */
    public function notify(Alert $alert): void;
}
