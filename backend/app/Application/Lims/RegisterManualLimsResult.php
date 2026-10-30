<?php

declare(strict_types=1);

namespace App\Application\Lims;

use App\Domain\Lims\Entity\LimsResult;
use App\Domain\Shared\Exception\ResourceNotFoundException;
use App\Domain\VaccineBatch\Repository\VaccineBatchRepository;
use DateTimeImmutable;

/**
 * Caso de uso de registro manual de un resultado microbiológico.
 *
 * El resultado queda registrado con origen "Control de Calidad (MSD)", que no
 * concede el gating: la aprobación que libera un lote es la que llega de
 * LIMS Labware (véase D5).
 */
final class RegisterManualLimsResult
{
    public function __construct(
        private readonly VaccineBatchRepository $batches,
        private readonly RegisterLimsResult $registerResult,
    ) {}

    /**
     * Registra el resultado indicado para el lote.
     *
     * @throws ResourceNotFoundException Si el lote no existe.
     */
    public function execute(
        string $batchId,
        string $testType,
        string $literal,
        ?string $approvedBy,
        DateTimeImmutable $resultDate,
        ?string $actorId = null,
        ?DateTimeImmutable $now = null,
    ): LimsResult {
        if ($this->batches->findById($batchId) === null) {
            throw ResourceNotFoundException::batch($batchId);
        }

        $result = LimsResult::registered($batchId, $testType, $literal, $approvedBy, $resultDate);

        return $this->registerResult->execute($result, $actorId, $now);
    }
}
