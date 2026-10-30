<?php

declare(strict_types=1);

namespace App\Domain\Security\Service;

use App\Domain\Security\ValueObject\SecurityGateVerdict;
use App\Domain\Security\ValueObject\SecurityStageResult;

/**
 * Regla de bloqueo de la promoción por el análisis de seguridad.
 *
 * El pipeline de entrega ejecuta el análisis de seguridad antes de promover
 * el código a `staging` y a `prod`. La regla sólo permite la promoción cuando
 * todas las etapas han terminado sin detectar vulnerabilidades; cualquier
 * etapa fallida, o la ausencia de etapas, bloquea la promoción (véase D20 y
 * NFR011).
 */
final class SecurityGate
{
    /**
     * Evalúa los resultados de las etapas de análisis.
     *
     * @param  list<SecurityStageResult>  $results
     */
    public function evaluate(array $results): SecurityGateVerdict
    {
        return new SecurityGateVerdict(array_values($results));
    }
}
