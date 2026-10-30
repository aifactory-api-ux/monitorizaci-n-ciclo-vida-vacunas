<?php

declare(strict_types=1);

namespace App\Application\Alerts;

use App\Domain\Alert\Entity\Alert;
use App\Domain\Alert\Repository\AlertRepository;

/**
 * Caso de uso de consulta del listado de alertas operativas.
 *
 * El listado devuelve todas las alertas registradas y, cuando se indica el
 * lote, sólo las de ese lote; en ambos casos el orden es cronológico.
 */
final class ListAlerts
{
    public function __construct(
        private readonly AlertRepository $alerts,
    ) {}

    /**
     * Alertas registradas, ordenadas cronológicamente.
     *
     * @return list<Alert>
     */
    public function execute(?string $batchId = null): array
    {
        if ($batchId === null || $batchId === '') {
            return $this->alerts->findAll();
        }

        return $this->alerts->findByBatchId($batchId);
    }
}
