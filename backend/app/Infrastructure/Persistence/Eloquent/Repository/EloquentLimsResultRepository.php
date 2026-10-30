<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repository;

use App\Domain\Lims\Entity\LimsResult;
use App\Domain\Lims\Repository\LimsResultRepository;
use App\Infrastructure\Persistence\Eloquent\Model\LimsResultModel;
use App\Infrastructure\Persistence\Eloquent\Model\LogEntryModel;
use DateTimeImmutable;

/**
 * Adaptador Eloquent del puerto de persistencia de los resultados de LIMS.
 *
 * La tabla `LIMS_RESULT` no tiene columnas de origen ni de fecha de
 * actualización, por lo que el adaptador reconstruye esa trazabilidad a partir
 * de las entradas del log estructurado que registró cada dato (véase D6).
 */
final class EloquentLimsResultRepository implements LimsResultRepository
{
    public function save(LimsResult $result): void
    {
        LimsResultModel::query()->create([
            'id' => $result->id(),
            'batch_id' => $result->batchId(),
            'test_type' => $result->testType(),
            'result' => $result->result(),
            'approved_by' => $result->approvedBy(),
            'result_date' => $result->resultDate(),
        ]);
    }

    public function findByBatchId(string $batchId): array
    {
        $models = LimsResultModel::query()
            ->where('batch_id', $batchId)
            ->orderBy('result_date')
            ->orderBy('id')
            ->get();

        if ($models->isEmpty()) {
            return [];
        }

        $provenance = $this->provenanceByResultId($batchId);

        return $models
            ->map(fn (LimsResultModel $model): LimsResult => $this->toEntity($model, $provenance))
            ->values()
            ->all();
    }

    /**
     * Trazabilidad de cada resultado del lote, indexada por identificador.
     *
     * @return array<string, array{source: string, updated_at: string|null}>
     */
    private function provenanceByResultId(string $batchId): array
    {
        $entries = LogEntryModel::query()
            ->where('batch_id', $batchId)
            ->whereIn('event_type', [LimsResult::EVENT_TYPE_IMPORTED, LimsResult::EVENT_TYPE_REGISTERED])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $provenance = [];

        foreach ($entries as $entry) {
            $payload = $entry->payload;
            $resultId = $payload['lims_result_id'] ?? null;

            if (! is_string($resultId) || $resultId === '') {
                continue;
            }

            $source = $payload['source'] ?? null;
            $updatedAt = $payload['updated_at'] ?? null;

            $provenance[$resultId] = [
                'source' => is_string($source) && $source !== '' ? $source : LimsResult::SOURCE_LABWARE,
                'updated_at' => is_string($updatedAt) && $updatedAt !== '' ? $updatedAt : null,
            ];
        }

        return $provenance;
    }

    /**
     * Reconstruye la entidad a partir del modelo persistido.
     *
     * @param  array<string, array{source: string, updated_at: string|null}>  $provenance
     */
    private function toEntity(LimsResultModel $model, array $provenance): LimsResult
    {
        $id = (string) $model->getKey();
        $resultDate = $this->toImmutable($model->result_date);
        $origin = $provenance[$id] ?? null;

        return LimsResult::reconstitute(
            $id,
            (string) $model->batch_id,
            (string) $model->test_type,
            (string) $model->result,
            $model->approved_by === null ? null : (string) $model->approved_by,
            $resultDate,
            $origin['source'] ?? LimsResult::SOURCE_LABWARE,
            $origin['updated_at'] === null ? $resultDate : new DateTimeImmutable($origin['updated_at']),
        );
    }

    /**
     * Convierte la marca temporal del modelo en una fecha inmutable.
     */
    private function toImmutable(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        return new DateTimeImmutable((string) $value);
    }
}
