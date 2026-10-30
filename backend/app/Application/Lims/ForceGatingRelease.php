<?php

declare(strict_types=1);

namespace App\Application\Lims;

use App\Domain\Audit\Service\StructuredAuditLogger;
use App\Domain\Shared\Exception\ResourceNotFoundException;
use App\Domain\Shared\Exception\UnauthorizedOperationException;
use App\Domain\VaccineBatch\Repository\VaccineBatchRepository;
use DateTimeImmutable;

/**
 * Caso de uso de forzaje de la liberación de un lote.
 *
 * La operación está siempre denegada: sólo la aprobación microbiológica
 * recibida de LIMS Labware libera un lote, de modo que nadie dentro del
 * sistema puede suplir esa ausencia (véase D5 y TC016). El intento queda
 * registrado en el log estructurado y el lote no se modifica.
 */
final class ForceGatingRelease
{
    /**
     * Tipo de evento registrado cuando se deniega la liberación.
     */
    public const EVENT_TYPE_DENIED = 'lims.gating.release.denied';

    public function __construct(
        private readonly VaccineBatchRepository $batches,
        private readonly EvaluateGatingRelease $evaluateGating,
        private readonly StructuredAuditLogger $auditLogger,
    ) {}

    /**
     * Deniega la forzaje de la liberación del lote.
     *
     * @throws ResourceNotFoundException Si el lote no existe.
     * @throws UnauthorizedOperationException Siempre: la operación está prohibida.
     */
    public function execute(
        string $batchId,
        ?string $actorId = null,
        ?DateTimeImmutable $now = null,
    ): never {
        if ($this->batches->findById($batchId) === null) {
            throw ResourceNotFoundException::batch($batchId);
        }

        $status = $this->evaluateGating->execute($batchId);

        $this->auditLogger->record(
            self::EVENT_TYPE_DENIED,
            [
                'gating_approved' => $status->isApproved(),
                'reason' => $status->reason(),
            ],
            $batchId,
            $actorId,
            $now ?? new DateTimeImmutable,
        );

        throw UnauthorizedOperationException::forceGatingRelease();
    }
}
