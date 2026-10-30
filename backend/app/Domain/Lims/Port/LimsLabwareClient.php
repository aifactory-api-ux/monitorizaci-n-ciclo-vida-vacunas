<?php

declare(strict_types=1);

namespace App\Domain\Lims\Port;

/**
 * Puerto de integración con LIMS Labware.
 *
 * Sólo expone operaciones de lectura (véase D7): la integración no puede
 * escribir, actualizar ni borrar nada en el sistema externo, de modo que la
 * condición de solo lectura queda garantizada por la forma del puerto y no
 * por una convención de uso.
 */
interface LimsLabwareClient
{
    /**
     * Cargas de resultados de los lotes disponibles en LIMS Labware.
     *
     * @param  string|null  $batchId  Lote concreto a consultar, o null para el conjunto.
     * @return list<array<string, mixed>>
     */
    public function fetchBatchResults(?string $batchId = null): array;

    /**
     * Aprobación microbiológica de un lote, o null si LIMS no la tiene registrada.
     *
     * @return array<string, mixed>|null
     */
    public function fetchMicrobiologyApproval(string $batchId): ?array;
}
