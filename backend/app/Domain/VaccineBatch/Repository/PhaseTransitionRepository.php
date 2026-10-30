<?php

declare(strict_types=1);

namespace App\Domain\VaccineBatch\Repository;

use App\Domain\VaccineBatch\Entity\PhaseTransition;

/**
 * Puerto de persistencia de las transiciones de fase.
 *
 * Sólo expone la operación de alta: las transiciones son inmutables y no
 * admiten actualización ni borrado (véase D4).
 */
interface PhaseTransitionRepository
{
    /**
     * Añade una transición al registro inmutable.
     */
    public function append(PhaseTransition $transition): void;

    /**
     * Transiciones de un lote ordenadas cronológicamente.
     *
     * @return list<PhaseTransition>
     */
    public function findByBatchId(string $batchId): array;

    /**
     * Transiciones registradas, ordenadas cronológicamente.
     *
     * La lectura completa alimenta el cálculo de los tiempos de ciclo del
     * panel (véase D21).
     *
     * @return list<PhaseTransition>
     */
    public function findAll(): array;
}
