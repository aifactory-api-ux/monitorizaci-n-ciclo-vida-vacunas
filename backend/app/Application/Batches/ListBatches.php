<?php

declare(strict_types=1);

namespace App\Application\Batches;

use App\Application\Authorization\BatchVisibilityPolicy;
use App\Domain\Search\Port\BatchSearchRepository;
use App\Domain\Search\ValueObject\BatchSearchCriteria;
use App\Domain\Search\ValueObject\BatchSearchResult;
use App\Domain\User\ValueObject\UserRole;
use App\Domain\VaccineBatch\Entity\VaccineBatch;
use App\Domain\VaccineBatch\Repository\VaccineBatchRepository;

/**
 * Caso de uso de consulta del listado de lotes.
 *
 * Expone el listado del lote acotado al alcance de visibilidad del rol y la
 * búsqueda paginada con filtros (véase D9 y D14).
 */
final class ListBatches
{
    public function __construct(
        private readonly VaccineBatchRepository $batches,
        private readonly BatchSearchRepository $search,
        private readonly BatchVisibilityPolicy $visibility,
    ) {}

    /**
     * Lotes visibles por el rol, del más reciente al más antiguo.
     *
     * @return list<VaccineBatch>
     */
    public function execute(UserRole $role): array
    {
        return $this->visibility->filter($role, $this->batches->findAll());
    }

    /**
     * Página de lotes que satisfacen los criterios de búsqueda indicados.
     */
    public function search(BatchSearchCriteria $criteria): BatchSearchResult
    {
        return $this->search->search($criteria);
    }
}
