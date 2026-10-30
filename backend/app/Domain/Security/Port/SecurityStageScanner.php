<?php

declare(strict_types=1);

namespace App\Domain\Security\Port;

use App\Domain\Security\ValueObject\SecurityStageResult;

/**
 * Analizador de una etapa de análisis de seguridad.
 *
 * Cada analizador ejecuta la comprobación de su etapa y devuelve el resultado
 * con las vulnerabilidades detectadas, si las hay (véase D20).
 */
interface SecurityStageScanner
{
    /**
     * Ejecuta el análisis y devuelve su resultado.
     */
    public function scan(): SecurityStageResult;
}
