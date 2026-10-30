<?php

declare(strict_types=1);

namespace App\Application\Alerts;

use App\Domain\Alert\Entity\Alert;

/**
 * Caso de uso de consulta del histórico de alertas operativas.
 *
 * El histórico muestra todas las alertas generadas con sus detalles y admite
 * acotarse a un lote concreto; las alertas se devuelven ordenadas
 * cronológicamente por su fecha y hora de detección (véase TC010).
 */
final class GetAlertHistory
{
    public function __construct(
        private readonly ListAlerts $alerts,
    ) {}

    /**
     * Histórico de alertas, opcionalmente acotado a un lote.
     *
     * @return list<Alert>
     */
    public function execute(?string $batchId = null): array
    {
        return $this->alerts->execute($batchId);
    }
}
