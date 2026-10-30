<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Domain\Lims\Port\LimsLabwareClient;

/**
 * Doble programable del cliente de LIMS Labware.
 *
 * Sustituye al adaptador HTTP durante las pruebas: permite preparar cargas
 * válidas, cargas inconsistentes y la aprobación microbiológica. Registra las
 * operaciones invocadas para comprobar que la integración es de sólo lectura
 * (véase D7).
 */
final class FakeLabwareLimsClient implements LimsLabwareClient
{
    /**
     * Prefijo de las operaciones que alteran el estado del sistema externo.
     */
    private const WRITE_OPERATION_PREFIX = 'write';

    /**
     * Cargas que devolverá la consulta de resultados.
     *
     * @var list<array<string, mixed>>
     */
    private array $payloads = [];

    /**
     * Aprobación microbiológica devuelta para cualquier lote.
     *
     * @var array<string, mixed>|null
     */
    private ?array $approval = null;

    /**
     * Operaciones invocadas sobre el doble.
     *
     * @var list<string>
     */
    private array $operations = [];

    /**
     * Prepara las cargas que devolverá la consulta de resultados.
     *
     * @param  list<array<string, mixed>>  $payloads
     */
    public function withPayloads(array $payloads): self
    {
        $this->payloads = array_values($payloads);

        return $this;
    }

    /**
     * Añade una carga al conjunto que devolverá la consulta de resultados.
     *
     * @param  array<string, mixed>  $payload
     */
    public function withPayload(array $payload): self
    {
        $this->payloads[] = $payload;

        return $this;
    }

    /**
     * Prepara la aprobación microbiológica devuelta para un lote.
     *
     * @param  array<string, mixed>|null  $approval
     */
    public function withMicrobiologyApproval(?array $approval): self
    {
        $this->approval = $approval;

        return $this;
    }

    /**
     * Cargas de resultados de los lotes disponibles en LIMS Labware.
     *
     * @return list<array<string, mixed>>
     */
    public function fetchBatchResults(?string $batchId = null): array
    {
        $this->operations[] = 'read:batch_results';

        if ($batchId === null) {
            return $this->payloads;
        }

        return array_values(array_filter(
            $this->payloads,
            fn (array $payload): bool => ($payload['batch_id'] ?? null) === $batchId,
        ));
    }

    /**
     * Aprobación microbiológica del lote.
     *
     * @return array<string, mixed>|null
     */
    public function fetchMicrobiologyApproval(string $batchId): ?array
    {
        $this->operations[] = 'read:microbiology_approval';

        return $this->approval;
    }

    /**
     * Operaciones invocadas sobre el doble.
     *
     * @return list<string>
     */
    public function operations(): array
    {
        return $this->operations;
    }

    /**
     * Número de operaciones de escritura invocadas.
     *
     * El puerto sólo expone lecturas, de modo que este valor es siempre cero:
     * es la comprobación de que la integración no altera LIMS Labware.
     */
    public function writeOperations(): int
    {
        return count(array_filter(
            $this->operations,
            fn (string $operation): bool => str_starts_with($operation, self::WRITE_OPERATION_PREFIX),
        ));
    }
}
