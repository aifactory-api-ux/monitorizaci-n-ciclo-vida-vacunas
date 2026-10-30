<?php

declare(strict_types=1);

namespace App\Domain\VaccineBatch\Entity;

use App\Domain\Shared\Exception\DomainException;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\VaccineBatch\Service\PhaseTransitionPolicy;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;
use App\Domain\VaccineBatch\ValueObject\BatchStatus;
use DateTimeImmutable;

/**
 * Lote de vacuna biológica.
 *
 * Es la raíz del agregado: custodia la fase, el estado y la ubicación
 * vigente del lote, y es la única que puede modificar su ciclo de vida.
 * Las transiciones de fase sólo se admiten a través de la
 * `PhaseTransitionPolicy` (véase D4).
 */
final class VaccineBatch
{
    private function __construct(
        private readonly string $id,
        private readonly string $referenceId,
        private BatchPhase $phase,
        private BatchStatus $status,
        private string $currentCampusId,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    /**
     * Da de alta un lote en la fase de cultivo del Campus 1.
     *
     * @throws DomainException Si la referencia o el campus no son identificadores válidos.
     */
    public static function create(string $referenceId, string $currentCampusId, DateTimeImmutable $now): self
    {
        self::assertIdentifier($referenceId, 'referencia de vacuna');
        self::assertIdentifier($currentCampusId, 'campus');

        return new self(
            Uuid::v7(),
            $referenceId,
            BatchPhase::from(BatchPhase::CULTURE),
            BatchStatus::from(BatchStatus::IN_PROGRESS),
            $currentCampusId,
            $now,
            $now,
        );
    }

    /**
     * Da de alta un lote a partir de la carga de LIMS Labware.
     *
     * El lote importado nace en la fase de cultivo del Campus 1 y conserva el
     * identificador con el que LIMS Labware lo referencia, de modo que la
     * trazabilidad entre ambos sistemas no depende de una tabla puente.
     *
     * @throws DomainException Si el identificador del lote no es un UUID válido.
     */
    public static function imported(
        string $id,
        string $referenceId,
        string $currentCampusId,
        DateTimeImmutable $now,
    ): self {
        if (! Uuid::isValid($id)) {
            throw DomainException::invalidUuid($id);
        }

        self::assertIdentifier($referenceId, 'referencia de vacuna');
        self::assertIdentifier($currentCampusId, 'campus');

        return new self(
            $id,
            $referenceId,
            BatchPhase::from(BatchPhase::CULTURE),
            BatchStatus::from(BatchStatus::IN_PROGRESS),
            $currentCampusId,
            $now,
            $now,
        );
    }

    /**
     * Reconstruye el lote a partir de su estado persistido.
     */
    public static function reconstitute(
        string $id,
        string $referenceId,
        string $phase,
        string $status,
        string $currentCampusId,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        self::assertIdentifier($id, 'lote');

        return new self(
            $id,
            $referenceId,
            BatchPhase::from($phase),
            BatchStatus::from($status),
            $currentCampusId,
            $createdAt,
            $updatedAt,
        );
    }

    /**
     * Identificador UUID del lote.
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Identificador UUID de la referencia de vacuna asociada.
     */
    public function referenceId(): string
    {
        return $this->referenceId;
    }

    /**
     * Fase vigente del lote.
     */
    public function phase(): BatchPhase
    {
        return $this->phase;
    }

    /**
     * Estado vigente del lote.
     */
    public function status(): BatchStatus
    {
        return $this->status;
    }

    /**
     * Identificador UUID del campus que aloja el lote.
     */
    public function currentCampusId(): string
    {
        return $this->currentCampusId;
    }

    /**
     * Fecha de alta del lote.
     */
    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Fecha de la última modificación del lote.
     */
    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Avanza el lote a la fase siguiente y sincroniza su ubicación.
     *
     * @throws DomainException Si la transición no es secuencial.
     */
    public function advanceTo(BatchPhase $target, string $campusId, PhaseTransitionPolicy $policy, DateTimeImmutable $now): void
    {
        $policy->assertCanAdvance($this->phase, $target);
        self::assertIdentifier($campusId, 'campus');

        $this->phase = $target;
        $this->currentCampusId = $campusId;

        if ($this->status->equals(BatchStatus::from(BatchStatus::BLOCKED_BY_GATING))) {
            $this->status = BatchStatus::from(BatchStatus::IN_PROGRESS);
        }

        $this->updatedAt = $now;
    }

    /**
     * Deja el lote bloqueado a la espera de la aprobación microbiológica.
     */
    public function blockByGating(DateTimeImmutable $now): void
    {
        $this->status = BatchStatus::from(BatchStatus::BLOCKED_BY_GATING);
        $this->updatedAt = $now;
    }

    /**
     * Registra la recepción física del lote en el Campus 3.
     *
     * @throws DomainException Si el lote no está en la fase logística.
     */
    public function registerPhysicalReception(DateTimeImmutable $now): void
    {
        if (! $this->phase->equals(BatchPhase::from(BatchPhase::LOGISTICS))) {
            throw DomainException::invalidBatchState(
                'La recepción física sólo puede registrarse sobre un lote en fase logística.',
                'invalid_reception_phase',
            );
        }

        $this->status = BatchStatus::from(BatchStatus::RECEIVED);
        $this->updatedAt = $now;
    }

    /**
     * Cierra el ciclo logístico del lote.
     *
     * @throws DomainException Si el lote no tiene la recepción física registrada.
     */
    public function closeLogisticsCycle(DateTimeImmutable $now): void
    {
        if (! $this->status->equals(BatchStatus::from(BatchStatus::RECEIVED))) {
            throw DomainException::invalidBatchState(
                'El cierre del ciclo logístico exige un lote con la recepción física registrada.',
                'invalid_logistics_closure',
            );
        }

        $this->status = BatchStatus::from(BatchStatus::CLOSED);
        $this->updatedAt = $now;
    }

    /**
     * Comprueba que un identificador de referencia no esté vacío.
     *
     * @throws DomainException Si el identificador está vacío.
     */
    private static function assertIdentifier(string $value, string $concept): void
    {
        if (trim($value) === '') {
            throw DomainException::invalidBatchState(
                sprintf('El %s del lote no puede estar vacío.', $concept),
                'invalid_batch_identifier',
            );
        }
    }
}
