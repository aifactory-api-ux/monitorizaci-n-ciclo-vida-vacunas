<?php

declare(strict_types=1);

namespace App\Domain\Lims\Service;

use App\Domain\Lims\Entity\LimsResult;
use App\Domain\Lims\ValueObject\GatingDecision;

/**
 * Evaluador del gating microbiológico de un lote.
 *
 * Determina si existe un resultado microbiológico aprobado y procedente de
 * LIMS Labware (véase D5). El registro manual del Técnico de Control de
 * Calidad no concede el gating: sólo la aprobación recibida del LIMS lo hace,
 * de modo que la liberación no puede forzarse desde el propio sistema.
 */
final class GatingEvaluator
{
    /**
     * Evalúa el gating a partir de los resultados registrados del lote.
     *
     * @param  list<LimsResult>  $results
     */
    public function evaluate(array $results): GatingDecision
    {
        $microbiology = null;

        foreach ($results as $result) {
            if (! $result->isMicrobiology()) {
                continue;
            }

            $microbiology = $result;
            break;
        }

        if ($microbiology === null) {
            return GatingDecision::rejected('El lote no tiene ningún resultado microbiológico registrado.');
        }

        if (! $microbiology->isApproved()) {
            return GatingDecision::rejected('El resultado microbiológico registrado no consta como aprobado.');
        }

        if (! $microbiology->comesFromLabware()) {
            return GatingDecision::rejected('La aprobación microbiológica registrada no procede de LIMS Labware.');
        }

        return GatingDecision::approved($microbiology);
    }
}
