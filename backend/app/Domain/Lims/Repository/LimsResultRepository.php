<?php

declare(strict_types=1);

namespace App\Domain\Lims\Repository;

use App\Domain\Lims\Entity\LimsResult;

/**
 * Puerto de persistencia de los resultados de LIMS.
 */
interface LimsResultRepository
{
    /**
     * Persiste el resultado, insertándolo si es nuevo.
     */
    public function save(LimsResult $result): void;

    /**
     * Resultados del lote ordenados por fecha de resultado.
     *
     * @return list<LimsResult>
     */
    public function findByBatchId(string $batchId): array;
}
