<?php

declare(strict_types=1);

namespace App\Domain\Lims\Entity;

use App\Domain\Shared\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * Resultado de un análisis registrado para un lote.
 *
 * La tabla `LIMS_RESULT` del contrato de esquema no tiene columnas de
 * procedencia ni de fecha de actualización, y el contrato no admite añadirlas
 * (véase D6). La trazabilidad de origen y fecha se conserva en el log
 * estructurado que escribe `RegisterLimsResult`, y la entidad las expone para
 * poder representarlas en la API.
 */
final class LimsResult
{
    /**
     * Origen de los datos importados desde LIMS Labware.
     */
    public const SOURCE_LABWARE = 'LIMS Labware';

    /**
     * Origen de los resultados registrados por el Técnico de Control de Calidad.
     */
    public const SOURCE_QUALITY_CONTROL = 'Control de Calidad (MSD)';

    /**
     * Tipo de análisis microbiológico.
     */
    public const TEST_TYPE_MICROBIOLOGY = 'Microbiología';

    /**
     * Literal de resultado aprobado.
     */
    public const RESULT_APPROVED = 'Aprobado';

    /**
     * Tipo de evento del log estructurado para los datos importados de LIMS.
     */
    public const EVENT_TYPE_IMPORTED = 'lims.result.imported';

    /**
     * Tipo de evento del log estructurado para los datos registrados a mano.
     */
    public const EVENT_TYPE_REGISTERED = 'lims.result.registered';

    private function __construct(
        private readonly string $id,
        private readonly string $batchId,
        private readonly string $testType,
        private readonly string $result,
        private readonly ?string $approvedBy,
        private readonly DateTimeImmutable $resultDate,
        private readonly string $source,
        private readonly DateTimeImmutable $updatedAt,
    ) {}

    /**
     * Resultado importado desde LIMS Labware.
     */
    public static function imported(
        string $batchId,
        string $testType,
        string $result,
        ?string $approvedBy,
        DateTimeImmutable $resultDate,
        ?DateTimeImmutable $updatedAt = null,
    ): self {
        return new self(
            Uuid::v7(),
            $batchId,
            $testType,
            $result,
            $approvedBy,
            $resultDate,
            self::SOURCE_LABWARE,
            $updatedAt ?? $resultDate,
        );
    }

    /**
     * Resultado registrado por el Técnico de Control de Calidad.
     */
    public static function registered(
        string $batchId,
        string $testType,
        string $result,
        ?string $approvedBy,
        DateTimeImmutable $resultDate,
        ?DateTimeImmutable $updatedAt = null,
    ): self {
        return new self(
            Uuid::v7(),
            $batchId,
            $testType,
            $result,
            $approvedBy,
            $resultDate,
            self::SOURCE_QUALITY_CONTROL,
            $updatedAt ?? $resultDate,
        );
    }

    /**
     * Reconstruye el resultado a partir de su estado persistido.
     */
    public static function reconstitute(
        string $id,
        string $batchId,
        string $testType,
        string $result,
        ?string $approvedBy,
        DateTimeImmutable $resultDate,
        string $source,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            $id,
            $batchId,
            $testType,
            $result,
            $approvedBy,
            $resultDate,
            $source,
            $updatedAt,
        );
    }

    /**
     * Identificador UUID del resultado.
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Identificador UUID del lote al que pertenece.
     */
    public function batchId(): string
    {
        return $this->batchId;
    }

    /**
     * Tipo de análisis.
     */
    public function testType(): string
    {
        return $this->testType;
    }

    /**
     * Literal del resultado.
     */
    public function result(): string
    {
        return $this->result;
    }

    /**
     * Identificador de quien aprueba el resultado, si lo hay.
     */
    public function approvedBy(): ?string
    {
        return $this->approvedBy;
    }

    /**
     * Fecha del resultado.
     */
    public function resultDate(): DateTimeImmutable
    {
        return $this->resultDate;
    }

    /**
     * Origen del dato.
     */
    public function source(): string
    {
        return $this->source;
    }

    /**
     * Fecha de actualización del dato en su origen.
     */
    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Indica si el análisis es microbiológico.
     */
    public function isMicrobiology(): bool
    {
        return $this->testType === self::TEST_TYPE_MICROBIOLOGY;
    }

    /**
     * Indica si el resultado está aprobado por un responsable identificado.
     */
    public function isApproved(): bool
    {
        return $this->result === self::RESULT_APPROVED
            && $this->approvedBy !== null
            && trim($this->approvedBy) !== '';
    }

    /**
     * Indica si el dato procede de LIMS Labware.
     */
    public function comesFromLabware(): bool
    {
        return $this->source === self::SOURCE_LABWARE;
    }
}
