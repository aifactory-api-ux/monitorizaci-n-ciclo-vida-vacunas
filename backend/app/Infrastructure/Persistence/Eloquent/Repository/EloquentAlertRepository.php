<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repository;

use App\Domain\Alert\Entity\Alert;
use App\Domain\Alert\Repository\AlertRepository;
use App\Domain\Alert\ValueObject\AlertSeverity;
use App\Domain\Alert\ValueObject\AlertType;
use App\Infrastructure\Persistence\Eloquent\Model\AlertModel;
use DateTimeImmutable;

/**
 * Adaptador Eloquent del puerto de persistencia de las alertas operativas.
 *
 * El histórico se devuelve siempre ordenado cronológicamente por `created_at`,
 * con el identificador como desempate estable para dos detizaciones del mismo
 * instante.
 */
final class EloquentAlertRepository implements AlertRepository
{
    public function save(Alert $alert): void
    {
        AlertModel::query()->create([
            'id' => $alert->id(),
            'batch_id' => $alert->batchId(),
            'alert_type' => $alert->alertType()->value(),
            'severity' => $alert->severity()->value(),
            'message' => $alert->message(),
            'created_at' => $alert->createdAt(),
        ]);
    }

    public function findAll(): array
    {
        return $this->toAlerts(
            AlertModel::query()
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
        );
    }

    public function findByBatchId(string $batchId): array
    {
        return $this->toAlerts(
            AlertModel::query()
                ->where('batch_id', $batchId)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
        );
    }

    /**
     * Convierte los modelos persistidos en entidades de dominio.
     *
     * @param  iterable<AlertModel>  $models
     * @return list<Alert>
     */
    private function toAlerts(iterable $models): array
    {
        $alerts = [];

        foreach ($models as $model) {
            $alerts[] = Alert::reconstitute(
                (string) $model->getKey(),
                (string) $model->batch_id,
                AlertType::from((string) $model->alert_type),
                AlertSeverity::from((string) $model->severity),
                (string) $model->message,
                $this->toImmutable($model->created_at),
            );
        }

        return $alerts;
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
