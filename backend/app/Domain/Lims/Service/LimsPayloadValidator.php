<?php

declare(strict_types=1);

namespace App\Domain\Lims\Service;

use App\Domain\Lims\Entity\LimsResult;
use App\Domain\Lims\ValueObject\LimsPayloadValidation;
use App\Domain\Shared\ValueObject\Uuid;
use DateTimeImmutable;
use Throwable;

/**
 * Validador de consistencia de las cargas de LIMS Labware.
 *
 * Las reglas viven en el dominio (véase D8) porque son la condición de
 * descarte: una carga que no las satisface no se persiste ni se expone, y su
 * intento de importación queda registrado con el detalle del error.
 */
final class LimsPayloadValidator
{
    /**
     * La carga no identifica el lote.
     */
    public const ERROR_MISSING_BATCH_REFERENCE = 'missing_batch_reference';

    /**
     * El lote identificado en la carga no tiene un identificador válido.
     */
    public const ERROR_INVALID_BATCH_REFERENCE = 'invalid_batch_reference';

    /**
     * La carga no identifica la referencia de vacuna del lote.
     */
    public const ERROR_MISSING_VACCINE_REFERENCE = 'missing_vaccine_reference';

    /**
     * La carga no incluye un resultado microbiológico.
     */
    public const ERROR_MISSING_MICROBIOLOGY_RESULT = 'missing_microbiology_result';

    /**
     * El resultado microbiológico de la carga está vacío.
     */
    public const ERROR_EMPTY_RESULT = 'empty_result';

    /**
     * El resultado microbiológico de la carga no tiene una fecha válida.
     */
    public const ERROR_INVALID_RESULT_DATE = 'invalid_result_date';

    /**
     * Valida la consistencia de una carga de LIMS Labware.
     *
     * @param  array<string, mixed>  $payload
     */
    public function validate(array $payload): LimsPayloadValidation
    {
        $batchId = $this->readString($payload, 'batch_id');

        if ($batchId === null) {
            return LimsPayloadValidation::rejected(
                self::ERROR_MISSING_BATCH_REFERENCE,
                'La carga de LIMS Labware no identifica el lote.',
            );
        }

        if (! Uuid::isValid($batchId)) {
            return LimsPayloadValidation::rejected(
                self::ERROR_INVALID_BATCH_REFERENCE,
                sprintf('El lote "%s" de la carga de LIMS Labware no tiene un identificador válido.', $batchId),
            );
        }

        if ($this->readString($payload, 'reference_id') === null) {
            return LimsPayloadValidation::rejected(
                self::ERROR_MISSING_VACCINE_REFERENCE,
                'La carga de LIMS Labware no identifica la referencia de vacuna del lote.',
            );
        }

        $microbiology = $this->microbiologyResult($payload);

        if ($microbiology === null) {
            return LimsPayloadValidation::rejected(
                self::ERROR_MISSING_MICROBIOLOGY_RESULT,
                'La carga de LIMS Labware no incluye un resultado microbiológico.',
            );
        }

        if ($this->readString($microbiology, 'result') === null) {
            return LimsPayloadValidation::rejected(
                self::ERROR_EMPTY_RESULT,
                'El resultado microbiológico de la carga de LIMS Labware está vacío.',
            );
        }

        $resultDate = $this->readString($microbiology, 'result_date');

        if ($resultDate === null || ! $this->isParsableDate($resultDate)) {
            return LimsPayloadValidation::rejected(
                self::ERROR_INVALID_RESULT_DATE,
                'El resultado microbiológico de la carga de LIMS Labware no tiene una fecha de resultado válida.',
            );
        }

        return LimsPayloadValidation::accepted();
    }

    /**
     * Resultado microbiológico de la carga, o null si no lo incluye.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function microbiologyResult(array $payload): ?array
    {
        $results = $payload['results'] ?? null;

        if (! is_array($results)) {
            return null;
        }

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            if (($result['test_type'] ?? null) !== LimsResult::TEST_TYPE_MICROBIOLOGY) {
                continue;
            }

            return $result;
        }

        return null;
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
     * Indica si el texto es una fecha que se puede interpretar.
     */
    private function isParsableDate(string $value): bool
    {
        try {
            new DateTimeImmutable($value);
        } catch (Throwable) {
            return false;
        }

        return true;
    }
}
