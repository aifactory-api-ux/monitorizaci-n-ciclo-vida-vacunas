<?php

declare(strict_types=1);

namespace App\Application\Lims;

use App\Domain\Lims\Repository\LimsResultRepository;
use App\Domain\Lims\Service\GatingEvaluator;

/**
 * Caso de uso de evaluación del gating microbiológico de un lote.
 */
final class EvaluateGatingRelease
{
    public function __construct(
        private readonly LimsResultRepository $results,
        private readonly GatingEvaluator $evaluator,
    ) {}

    /**
     * Evalúa el gating a partir de los resultados registrados del lote.
     */
    public function execute(string $batchId): GatingStatus
    {
        return new GatingStatus($batchId, $this->evaluator->evaluate($this->results->findByBatchId($batchId)));
    }
}
