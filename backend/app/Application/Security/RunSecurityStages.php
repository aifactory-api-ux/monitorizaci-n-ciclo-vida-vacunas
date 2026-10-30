<?php

declare(strict_types=1);

namespace App\Application\Security;

use App\Domain\Security\Port\SecurityStageScanner;
use App\Domain\Security\Service\SecurityGate;
use App\Domain\Security\ValueObject\SecurityGateVerdict;

/**
 * Caso de uso de ejecución de las etapas de análisis de seguridad.
 *
 * Ejecuta cada etapa declarada por el despliegue y aplica la regla de
 * bloqueo de la promoción: sólo una etapa que termina sin detectar
 * vulnerabilidades permite promover el código (véase D20 y NFR011).
 */
final class RunSecurityStages
{
    /**
     * @param  list<SecurityStageScanner>  $scanners
     */
    public function __construct(
        private readonly array $scanners,
        private readonly SecurityGate $gate,
    ) {}

    /**
     * Ejecuta las etapas y devuelve el veredicto de la promoción.
     */
    public function execute(): SecurityGateVerdict
    {
        $results = [];

        foreach ($this->scanners as $scanner) {
            $results[] = $scanner->scan();
        }

        return $this->gate->evaluate($results);
    }
}
