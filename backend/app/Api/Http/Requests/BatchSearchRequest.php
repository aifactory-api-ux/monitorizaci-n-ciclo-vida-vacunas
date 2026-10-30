<?php

declare(strict_types=1);

namespace App\Api\Http\Requests;

use App\Domain\Alert\ValueObject\AlertStatus;
use App\Domain\Search\ValueObject\BatchSearchCriteria;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Solicitud de búsqueda avanzada de lotes.
 *
 * Valida los filtros del listado y los traduce a los criterios de búsqueda
 * del dominio (véase D14).
 */
class BatchSearchRequest extends FormRequest
{
    /**
     * La autorización por rol se aplica en la capa de aplicación (véase D9).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'family' => ['sometimes', 'nullable', 'string', 'max:100'],
            'phase' => ['sometimes', 'nullable', 'string', Rule::in(BatchPhase::values())],
            'alert_status' => ['sometimes', 'nullable', 'string', Rule::in(AlertStatus::values())],
            'date_from' => ['sometimes', 'nullable', 'string', 'date'],
            'date_to' => ['sometimes', 'nullable', 'string', 'date'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.BatchSearchCriteria::MAX_PER_PAGE],
        ];
    }

    /**
     * Comprueba que el intervalo de fechas sea utilizable.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $dateFrom = $this->stringOrNull('date_from');
            $dateTo = $this->stringOrNull('date_to');

            if ($dateFrom === null || $dateTo === null) {
                return;
            }

            if ($this->parseDate($dateFrom, false) > $this->parseDate($dateTo, true)) {
                $validator->errors()->add(
                    'date_from',
                    'La fecha inicial del intervalo no puede ser posterior a la fecha final.',
                );
            }
        });
    }

    /**
     * Criterios de búsqueda derivados de los filtros validados.
     */
    public function criteria(): BatchSearchCriteria
    {
        $dateFrom = $this->stringOrNull('date_from');
        $dateTo = $this->stringOrNull('date_to');

        return BatchSearchCriteria::create(
            family: $this->stringOrNull('family'),
            phase: $this->phase(),
            alertStatus: $this->alertStatus(),
            dateFrom: $dateFrom === null ? null : $this->parseDate($dateFrom, false),
            dateTo: $dateTo === null ? null : $this->parseDate($dateTo, true),
            page: $this->integerOrDefault('page', BatchSearchCriteria::DEFAULT_PAGE),
            perPage: $this->integerOrDefault('per_page', BatchSearchCriteria::DEFAULT_PER_PAGE),
        );
    }

    /**
     * Fase solicitada, o null si no se filtra.
     */
    private function phase(): ?BatchPhase
    {
        $phase = $this->stringOrNull('phase');

        return $phase === null ? null : BatchPhase::from($phase);
    }

    /**
     * Estado de alerta solicitado, o null si no se filtra.
     */
    private function alertStatus(): ?AlertStatus
    {
        $alertStatus = $this->stringOrNull('alert_status');

        return $alertStatus === null ? null : AlertStatus::from($alertStatus);
    }

    /**
     * Valor de texto de un parámetro, o null si no se ha indicado.
     */
    private function stringOrNull(string $key): ?string
    {
        $value = $this->query($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Valor entero de un parámetro, o el valor por defecto indicado.
     */
    private function integerOrDefault(string $key, int $default): int
    {
        $value = $this->query($key);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * Convierte el parámetro de fecha en una marca temporal.
     *
     * Un valor sin hora se interpreta como el inicio del día; el final del
     * intervalo se sitúa al cierre del día, de modo que el filtro por fecha
     * incluye los lotes dados de alta ese mismo día.
     */
    private function parseDate(string $value, bool $endOfDay): DateTimeImmutable
    {
        $date = new DateTimeImmutable($value);

        if (preg_match('/\d{1,2}:\d{2}/', $value) === 1) {
            return $date;
        }

        return $endOfDay
            ? $date->setTime(23, 59, 59)
            : $date->setTime(0, 0, 0);
    }
}
