<?php

declare(strict_types=1);

namespace App\Domain\Search\ValueObject;

use App\Domain\Alert\ValueObject\AlertStatus;
use App\Domain\Shared\Exception\DomainException;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;
use DateTimeImmutable;

/**
 * Criterios de la búsqueda avanzada de lotes.
 *
 * Reúne los filtros del listado (familia de producto, fase, estado de
 * alerta e intervalo de fechas de alta) y la paginación solicitada. Los
 * criterios son inmutables y se identifican por un hash estable, que es la
 * clave de la caché de resultados (véase D14).
 */
final class BatchSearchCriteria
{
    /**
     * Página por defecto cuando el cliente no la indica.
     */
    public const DEFAULT_PAGE = 1;

    /**
     * Número de resultados por página por defecto.
     */
    public const DEFAULT_PER_PAGE = 20;

    /**
     * Número máximo de resultados por página.
     */
    public const MAX_PER_PAGE = 100;

    private function __construct(
        private readonly ?string $family,
        private readonly ?BatchPhase $phase,
        private readonly ?AlertStatus $alertStatus,
        private readonly ?DateTimeImmutable $dateFrom,
        private readonly ?DateTimeImmutable $dateTo,
        private readonly int $page,
        private readonly int $perPage,
    ) {}

    /**
     * Construye los criterios de la búsqueda.
     *
     * @throws DomainException Si la paginación o el intervalo de fechas no son utilizables.
     */
    public static function create(
        ?string $family = null,
        ?BatchPhase $phase = null,
        ?AlertStatus $alertStatus = null,
        ?DateTimeImmutable $dateFrom = null,
        ?DateTimeImmutable $dateTo = null,
        int $page = self::DEFAULT_PAGE,
        int $perPage = self::DEFAULT_PER_PAGE,
    ): self {
        if ($page < 1) {
            throw DomainException::invalidSearchCriteria('La página de la búsqueda debe ser un número positivo.');
        }

        if ($perPage < 1 || $perPage > self::MAX_PER_PAGE) {
            throw DomainException::invalidSearchCriteria(sprintf(
                'El número de resultados por página debe estar entre 1 y %d.',
                self::MAX_PER_PAGE,
            ));
        }

        if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            throw DomainException::invalidSearchCriteria(
                'La fecha inicial del intervalo no puede ser posterior a la fecha final.',
            );
        }

        $normalizedFamily = $family === null ? null : trim($family);

        return new self(
            $normalizedFamily === '' ? null : $normalizedFamily,
            $phase,
            $alertStatus,
            $dateFrom,
            $dateTo,
            $page,
            $perPage,
        );
    }

    /**
     * Familia de producto filtrada, o null si no se filtra.
     */
    public function family(): ?string
    {
        return $this->family;
    }

    /**
     * Fase filtrada, o null si no se filtra.
     */
    public function phase(): ?BatchPhase
    {
        return $this->phase;
    }

    /**
     * Estado de alerta filtrado, o null si no se filtra.
     */
    public function alertStatus(): ?AlertStatus
    {
        return $this->alertStatus;
    }

    /**
     * Fecha inicial del intervalo de alta, o null si no se filtra.
     */
    public function dateFrom(): ?DateTimeImmutable
    {
        return $this->dateFrom;
    }

    /**
     * Fecha final del intervalo de alta, o null si no se filtra.
     */
    public function dateTo(): ?DateTimeImmutable
    {
        return $this->dateTo;
    }

    /**
     * Página solicitada.
     */
    public function page(): int
    {
        return $this->page;
    }

    /**
     * Número de resultados por página solicitado.
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * Número de resultados que se omiten para la página solicitada.
     */
    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * Clave estable de los criterios, que identifica la página cacheada.
     */
    public function cacheKey(): string
    {
        $canonical = json_encode([
            'family' => $this->family,
            'phase' => $this->phase?->value(),
            'alert_status' => $this->alertStatus?->value(),
            'date_from' => $this->dateFrom?->format(DATE_ATOM),
            'date_to' => $this->dateTo?->format(DATE_ATOM),
            'page' => $this->page,
            'per_page' => $this->perPage,
        ], JSON_THROW_ON_ERROR);

        return hash('sha256', $canonical);
    }
}
