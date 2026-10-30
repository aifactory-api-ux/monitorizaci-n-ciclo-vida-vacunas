<?php

declare(strict_types=1);

namespace App\Application\Lims;

use App\Domain\Audit\Service\StructuredAuditLogger;
use App\Domain\Lims\Entity\LimsResult;
use App\Domain\Lims\Repository\LimsResultRepository;
use DateTimeImmutable;

/**
 * Caso de uso de registro de un resultado de LIMS.
 *
 * Persiste el resultado y anota en el log estructurado su origen y su fecha de
 * actualización, que son los datos de trazabilidad que la tabla `LIMS_RESULT`
 * no puede almacenar (véase D6).
 */
final class RegisterLimsResult
{
    public function __construct(
        private readonly LimsResultRepository $results,
        private readonly StructuredAuditLogger $auditLogger,
    ) {}

    /**
     * Registra el resultado y su trazabilidad de origen.
     */
    public function execute(
        LimsResult $result,
        ?string $actorId = null,
        ?DateTimeImmutable $now = null,
    ): LimsResult {
        $timestamp = $now ?? new DateTimeImmutable;

        $this->results->save($result);

        $this->auditLogger->record(
            $result->comesFromLabware() ? LimsResult::EVENT_TYPE_IMPORTED : LimsResult::EVENT_TYPE_REGISTERED,
            [
                'lims_result_id' => $result->id(),
                'test_type' => $result->testType(),
                'result' => $result->result(),
                'approved_by' => $result->approvedBy(),
                'result_date' => $result->resultDate()->format(DATE_ATOM),
                'source' => $result->source(),
                'updated_at' => $result->updatedAt()->format(DATE_ATOM),
            ],
            $result->batchId(),
            $actorId,
            $timestamp,
        );

        return $result;
    }
}
