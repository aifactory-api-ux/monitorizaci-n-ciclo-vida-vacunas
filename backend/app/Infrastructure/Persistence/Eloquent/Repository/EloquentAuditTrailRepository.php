<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repository;

use App\Domain\Audit\Entity\LogEntry;
use App\Domain\Audit\Repository\AuditTrailRepository;
use App\Infrastructure\Persistence\Eloquent\Model\LogEntryModel;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Adaptador Eloquent del puerto de consulta del log estructurado.
 *
 * Sólo lee entradas ya registradas: la escritura sigue siendo exclusivamente
 * de `LogEntryRepository` (véase D10).
 */
final class EloquentAuditTrailRepository implements AuditTrailRepository
{
    public function findByBatchId(string $batchId): array
    {
        return $this->toEntries(
            LogEntryModel::query()
                ->where('batch_id', $batchId)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(),
        );
    }

    public function findByEventType(string $eventType): array
    {
        return $this->toEntries(
            LogEntryModel::query()
                ->where('event_type', $eventType)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(),
        );
    }

    /**
     * Devuelve la totalidad del historial, de la entrada más reciente a la más antigua.
     *
     * @return list<LogEntry>
     */
    public function findAll(int $limit, int $offset = 0): array
    {
        return $this->toEntries(
            LogEntryModel::query()
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->offset($offset)
                ->get(),
        );
    }

    /**
     * Número total de entradas registradas en el log.
     */
    public function count(): int
    {
        return LogEntryModel::query()->count();
    }

    /**
     * Busca una entrada del log por su identificador.
     */
    public function findById(string $id): ?LogEntry
    {
        $model = LogEntryModel::query()->find($id);

        return $model === null ? null : $this->toEntity($model);
    }

    /**
     * Reconstruye las entradas registradas a partir de los modelos.
     *
     * @param  Collection<int, LogEntryModel>  $models
     * @return list<LogEntry>
     */
    private function toEntries(Collection $models): array
    {
        return $models
            ->map(fn (LogEntryModel $model): LogEntry => $this->toEntity($model))
            ->values()
            ->all();
    }

    /**
     * Reconstruye la entrada a partir del modelo persistido.
     */
    private function toEntity(LogEntryModel $model): LogEntry
    {
        $payload = $model->payload;

        return LogEntry::reconstitute(
            (string) $model->getKey(),
            $model->batch_id === null ? null : (string) $model->batch_id,
            $model->user_id === null ? null : (string) $model->user_id,
            (string) $model->event_type,
            is_array($payload) ? $payload : [],
            $this->toImmutable($model->created_at),
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
