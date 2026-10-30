<?php

declare(strict_types=1);

namespace App\Domain\VaccineBatch\Entity;

use App\Domain\Shared\Exception\DomainException;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;
use DateTimeImmutable;

/**
 * Transición de fase de un lote.
 *
 * Es el registro inmutable del passage de un lote de una fase a la
 * siguiente: no admite modificación ni borrado una vez creada (véase D4).
 */
final class PhaseTransition
{
    private function __construct(
        private readonly string $id,
        private readonly string $batchId,
        private readonly BatchPhase $fromPhase,
        private readonly BatchPhase $toPhase,
        private readonly DateTimeImmutable $transitionDate,
    ) {}

    /**
     * Registra una transición de fase.
     *
     * @throws DomainException Si el lote o las fases no son identificadores válidos.
     */
    public static function record(
        string $batchId,
        BatchPhase $fromPhase,
        BatchPhase $toPhase,
        DateTimeImmutable $transitionDate,
    ): self {
        if (trim($batchId) === '') {
            throw DomainException::invalidBatchState(
                'La transición de fase necesita el lote al que pertenece.',
                'invalid_transition_batch',
            );
        }

        return new self(Uuid::v7(), $batchId, $fromPhase, $toPhase, $transitionDate);
    }

    /**
     * Reconstruye la transición a partir de su estado persistido.
     */
    public static function reconstitute(
        string $id,
        string $batchId,
        string $fromPhase,
        string $toPhase,
        DateTimeImmutable $transitionDate,
    ): self {
        return new self(
            $id,
            $batchId,
            BatchPhase::from($fromPhase),
            BatchPhase::from($toPhase),
            $transitionDate,
        );
    }

    /**
     * Identificador UUID de la transición.
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Identificador UUID del lote transicionado.
     */
    public function batchId(): string
    {
        return $this->batchId;
    }

    /**
     * Fase de origen de la transición.
     */
    public function fromPhase(): BatchPhase
    {
        return $this->fromPhase;
    }

    /**
     * Fase de destino de la transición.
     */
    public function toPhase(): BatchPhase
    {
        return $this->toPhase;
    }

    /**
     * Fecha y hora de la transición.
     */
    public function transitionDate(): DateTimeImmutable
    {
        return $this->transitionDate;
    }
}
