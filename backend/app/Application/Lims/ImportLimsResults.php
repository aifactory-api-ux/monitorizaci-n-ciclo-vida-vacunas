<?php

declare(strict_types=1);

namespace App\Application\Lims;

use App\Application\Batches\CreateBatch;
use App\Domain\Audit\Service\StructuredAuditLogger;
use App\Domain\Lims\Entity\LimsResult;
use App\Domain\Lims\Port\LimsLabwareClient;
use App\Domain\Lims\Service\LimsPayloadValidator;
use App\Domain\Shared\Exception\ConfigurationException;
use App\Domain\VaccineBatch\Entity\Campus;
use App\Domain\VaccineBatch\Entity\VaccineBatch;
use App\Domain\VaccineBatch\Repository\CampusRepository;
use App\Domain\VaccineBatch\Repository\VaccineBatchRepository;
use App\Domain\VaccineBatch\Repository\VaccineReferenceRepository;
use DateTimeImmutable;
use Throwable;

/**
 * Caso de uso de importación de resultados de LIMS Labware.
 *
 * La importación es de sólo lectura respecto de LIMS Labware: el sistema
 * recoge las cargas y las persiste. Una carga que no satisface las reglas de
 * consistencia no se persiste ni se expone, y su intento queda registrado con
 * el detalle del error en el log estructurado (véase D7 y D8).
 */
final class ImportLimsResults
{
    /**
     * Tipo de evento registrado cuando una carga se descarta.
     */
    public const EVENT_TYPE_REJECTED = 'lims.import.rejected';

    /**
     * La carga identifica una referencia de vacuna que no está en el catálogo.
     */
    public const ERROR_UNKNOWN_VACCINE_REFERENCE = 'unknown_vaccine_reference';

    /**
     * La carga asigna al lote una referencia de vacuna distinta de la registrada.
     */
    public const ERROR_MISMATCHED_VACCINE_REFERENCE = 'mismatched_vaccine_reference';

    public function __construct(
        private readonly LimsLabwareClient $client,
        private readonly LimsPayloadValidator $validator,
        private readonly RegisterLimsResult $registerResult,
        private readonly VaccineBatchRepository $batches,
        private readonly VaccineReferenceRepository $references,
        private readonly CampusRepository $campuses,
        private readonly StructuredAuditLogger $auditLogger,
    ) {}

    /**
     * Importa las cargas disponibles en LIMS Labware.
     */
    public function execute(
        ?string $batchId = null,
        ?string $actorId = null,
        ?DateTimeImmutable $now = null,
    ): LimsImportSummary {
        $timestamp = $now ?? new DateTimeImmutable;
        $payloads = $this->client->fetchBatchResults($batchId);

        $importedBatches = 0;
        $importedResults = 0;
        $rejections = [];

        foreach ($payloads as $payload) {
            $validation = $this->validator->validate($payload);

            if (! $validation->isValid()) {
                $rejections[] = $this->reject(
                    $validation->errorCode() ?? 'inconsistent_payload',
                    $validation->errorDetail() ?? 'La carga de LIMS Labware es inconsistente.',
                    $payload,
                    $actorId,
                    $timestamp,
                );

                continue;
            }

            $resolved = $this->resolveBatch($payload, $actorId, $timestamp);

            [$batch, $rejection] = $resolved;

            if ($rejection !== null) {
                $rejections[] = $rejection;

                continue;
            }

            foreach ($this->resultsOf($payload, $batch->id()) as $result) {
                $this->registerResult->execute($result, $actorId, $timestamp);
                $importedResults++;
            }

            $importedBatches++;
        }

        return new LimsImportSummary($importedBatches, $importedResults, $rejections);
    }

    /**
     * Localiza o da de alta el lote de la carga.
     *
     * @param  array<string, mixed>  $payload
     * @return array{0: VaccineBatch|null, 1: array{error_code: string, error_detail: string, batch_reference: string|null}|null}
     */
    private function resolveBatch(
        array $payload,
        ?string $actorId,
        DateTimeImmutable $timestamp,
    ): array {
        $batchId = $this->readString($payload, 'batch_id');
        $referenceId = $this->readString($payload, 'reference_id');

        if ($batchId === null) {
            return [null, $this->reject(
                LimsPayloadValidator::ERROR_MISSING_BATCH_REFERENCE,
                'La carga de LIMS Labware no identifica el lote.',
                $payload,
                $actorId,
                $timestamp,
            )];
        }

        if ($referenceId === null) {
            return [null, $this->reject(
                LimsPayloadValidator::ERROR_MISSING_VACCINE_REFERENCE,
                'La carga de LIMS Labware no identifica la referencia de vacuna del lote.',
                $payload,
                $actorId,
                $timestamp,
            )];
        }

        $batch = $this->batches->findById($batchId);

        if ($batch !== null) {
            if ($batch->referenceId() !== $referenceId) {
                return [null, $this->reject(
                    self::ERROR_MISMATCHED_VACCINE_REFERENCE,
                    sprintf(
                        'La carga de LIMS Labware asigna al lote "%s" la referencia de vacuna "%s", que no es la registrada en el sistema ("%s").',
                        $batchId,
                        $referenceId,
                        $batch->referenceId(),
                    ),
                    $payload,
                    $actorId,
                    $timestamp,
                )];
            }

            return [$batch, null];
        }

        if ($this->references->findById($referenceId) === null) {
            return [null, $this->reject(
                self::ERROR_UNKNOWN_VACCINE_REFERENCE,
                sprintf(
                    'La carga de LIMS Labware asigna al lote "%s" la referencia de vacuna "%s", que no está dada de alta en el catálogo.',
                    $batchId,
                    $referenceId,
                ),
                $payload,
                $actorId,
                $timestamp,
            )];
        }

        $campus = $this->campuses->findByName(Campus::CULTURE);

        if ($campus === null) {
            throw ConfigurationException::missingCampus(Campus::CULTURE);
        }

        $batch = VaccineBatch::imported($batchId, $referenceId, $campus->id(), $timestamp);

        $this->batches->save($batch);

        $this->auditLogger->record(
            CreateBatch::EVENT_TYPE,
            [
                'phase' => $batch->phase()->value(),
                'status' => $batch->status()->value(),
                'current_campus_id' => $batch->currentCampusId(),
                'reference_id' => $batch->referenceId(),
                'origin' => LimsResult::SOURCE_LABWARE,
            ],
            $batch->id(),
            $actorId,
            $timestamp,
        );

        return [$batch, null];
    }

    /**
     * Resultados analíticos de la carga, ya convertidos en entidades.
     *
     * @param  array<string, mixed>  $payload
     * @return list<LimsResult>
     */
    private function resultsOf(array $payload, string $batchId): array
    {
        $results = $payload['results'] ?? null;

        if (! is_array($results)) {
            return [];
        }

        $entities = [];

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            $testType = $this->readString($result, 'test_type');
            $literal = $this->readString($result, 'result');
            $resultDate = $this->readDate($result, 'result_date');

            if ($testType === null || $literal === null || $resultDate === null) {
                continue;
            }

            $entities[] = LimsResult::imported(
                $batchId,
                $testType,
                $literal,
                $this->readString($result, 'approved_by'),
                $resultDate,
                $this->readDate($result, 'updated_at') ?? $resultDate,
            );
        }

        return $entities;
    }

    /**
     * Descarta la carga y deja constancia del motivo en el log estructurado.
     *
     * @param  array<string, mixed>  $payload
     * @return array{error_code: string, error_detail: string, batch_reference: string|null}
     */
    private function reject(
        string $errorCode,
        string $errorDetail,
        array $payload,
        ?string $actorId,
        DateTimeImmutable $timestamp,
    ): array {
        $batchReference = $this->readString($payload, 'batch_id');

        $this->auditLogger->record(
            self::EVENT_TYPE_REJECTED,
            [
                'error_code' => $errorCode,
                'error_detail' => $errorDetail,
                'batch_reference' => $batchReference,
                'reference_id' => $this->readString($payload, 'reference_id'),
            ],
            null,
            $actorId,
            $timestamp,
        );

        return [
            'error_code' => $errorCode,
            'error_detail' => $errorDetail,
            'batch_reference' => $batchReference,
        ];
    }

    /**
     * Lee un valor de texto no vacío de la carga.
     *
     * @param  array<string, mixed>  $payload
     */
    private function readString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Lee una fecha de la carga, o null si no es utilizable.
     *
     * @param  array<string, mixed>  $payload
     */
    private function readDate(array $payload, string $key): ?DateTimeImmutable
    {
        $value = $this->readString($payload, $key);

        if ($value === null) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }
}
