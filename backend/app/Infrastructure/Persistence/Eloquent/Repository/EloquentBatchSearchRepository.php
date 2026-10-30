<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repository;

use App\Domain\Alert\ValueObject\AlertSeverity;
use App\Domain\Search\Entity\BatchSearchItem;
use App\Domain\Search\Port\BatchSearchRepository;
use App\Domain\Search\ValueObject\BatchSearchCriteria;
use App\Domain\Search\ValueObject\BatchSearchResult;
use App\Domain\VaccineBatch\Entity\VaccineBatch;
use App\Infrastructure\Persistence\Eloquent\Model\VaccineBatchModel;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Adaptador Eloquent del puerto de consulta de la búsqueda avanzada.
 *
 * Resuelve los filtros sobre el modelo relacional y devuelve la página de
 * resultados junto con el total de coincidencias. El orden es estable: la
 * fecha de alta descendente y el identificador como desempate, de modo que
 * dos lotes dados de alta en el mismo instante mantienen siempre el mismo
 * orden (véase D14).
 */
final class EloquentBatchSearchRepository implements BatchSearchRepository
{
    public function search(BatchSearchCriteria $criteria): BatchSearchResult
    {
        $query = $this->baseQuery();

        $this->applyFamily($query, $criteria);
        $this->applyPhase($query, $criteria);
        $this->applyAlertStatus($query, $criteria);
        $this->applyDateRange($query, $criteria);

        $total = (clone $query)->reorder()->count('VACCINE_BATCH.id');

        $items = $query
            ->orderByDesc('VACCINE_BATCH.created_at')
            ->orderBy('VACCINE_BATCH.id')
            ->forPage($criteria->page(), $criteria->perPage())
            ->get()
            ->map(fn (VaccineBatchModel $model): BatchSearchItem => $this->toSearchItem($model))
            ->values()
            ->all();

        return new BatchSearchResult(
            $items,
            $total,
            $criteria->page(),
            $criteria->perPage(),
        );
    }

    /**
     * Consulta base con el catálogo de referencias incorporado.
     *
     * @return Builder<VaccineBatchModel>
     */
    private function baseQuery(): Builder
    {
        return VaccineBatchModel::query()
            ->join(
                'VACCINE_REFERENCE as vaccine_reference',
                'vaccine_reference.id',
                '=',
                'VACCINE_BATCH.reference_id',
            )
            ->select([
                'VACCINE_BATCH.id',
                'VACCINE_BATCH.reference_id',
                'VACCINE_BATCH.phase',
                'VACCINE_BATCH.status',
                'VACCINE_BATCH.current_campus_id',
                'VACCINE_BATCH.created_at',
                'VACCINE_BATCH.updated_at',
                'vaccine_reference.name as reference_name',
                'vaccine_reference.family as reference_family',
            ]);
    }

    /**
     * Filtra por familia de producto.
     *
     * @param  Builder<VaccineBatchModel>  $query
     */
    private function applyFamily(Builder $query, BatchSearchCriteria $criteria): void
    {
        $family = $criteria->family();

        if ($family === null) {
            return;
        }

        $query->where('vaccine_reference.family', $family);
    }

    /**
     * Filtra por fase del lote.
     *
     * @param  Builder<VaccineBatchModel>  $query
     */
    private function applyPhase(Builder $query, BatchSearchCriteria $criteria): void
    {
        $phase = $criteria->phase();

        if ($phase === null) {
            return;
        }

        $query->where('VACCINE_BATCH.phase', $phase->value());
    }

    /**
     * Filtra por estado de alerta del lote.
     *
     * La tabla `ALERT` no tiene columna de estado: la severidad persistida
     * representa el estado de la alerta (véase D6).
     *
     * @param  Builder<VaccineBatchModel>  $query
     */
    private function applyAlertStatus(Builder $query, BatchSearchCriteria $criteria): void
    {
        $alertStatus = $criteria->alertStatus();

        if ($alertStatus === null) {
            return;
        }

        $critical = AlertSeverity::from(AlertSeverity::CRITICAL);

        if ($alertStatus->requiresCriticalAlert()) {
            $query->whereExists(function (QueryBuilder $subquery) use ($critical): void {
                $subquery->selectRaw('1')
                    ->from('ALERT')
                    ->whereColumn('ALERT.batch_id', 'VACCINE_BATCH.id')
                    ->where('ALERT.severity', $critical->value());
            });

            return;
        }

        $query->whereNotExists(function (QueryBuilder $subquery) use ($critical): void {
            $subquery->selectRaw('1')
                ->from('ALERT')
                ->whereColumn('ALERT.batch_id', 'VACCINE_BATCH.id')
                ->where('ALERT.severity', $critical->value());
        });
    }

    /**
     * Filtra por intervalo de fechas de alta del lote.
     *
     * @param  Builder<VaccineBatchModel>  $query
     */
    private function applyDateRange(Builder $query, BatchSearchCriteria $criteria): void
    {
        $dateFrom = $criteria->dateFrom();
        $dateTo = $criteria->dateTo();

        if ($dateFrom !== null) {
            $query->where('VACCINE_BATCH.created_at', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->where('VACCINE_BATCH.created_at', '<=', $dateTo);
        }
    }

    /**
     * Proyecta el modelo sobre el elemento de resultado de la búsqueda.
     */
    private function toSearchItem(VaccineBatchModel $model): BatchSearchItem
    {
        $batch = VaccineBatch::reconstitute(
            (string) $model->getKey(),
            (string) $model->reference_id,
            (string) $model->phase,
            (string) $model->status,
            (string) $model->current_campus_id,
            $this->toImmutable($model->created_at),
            $this->toImmutable($model->updated_at),
        );

        return new BatchSearchItem(
            $batch,
            (string) $model->reference_name,
            (string) $model->reference_family,
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

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        return new DateTimeImmutable((string) $value);
    }
}
