<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Application\Batches\CreateBatch;
use App\Domain\Audit\Entity\LogEntry;
use App\Domain\Audit\Repository\LogEntryRepository;
use App\Domain\Audit\Service\AuditEventType;
use App\Domain\Shared\Exception\DomainException;
use App\Domain\Shared\ValueObject\Uuid;
use App\Infrastructure\Persistence\Eloquent\Model\LogEntryModel;
use App\Infrastructure\Persistence\Eloquent\Repository\EloquentLogEntryRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use ReflectionMethod;
use Tests\Support\InteractsWithDatabase;
use Tests\Support\MakesAuthenticatedRequests;
use Tests\TestCase;

/**
 * Log estructurado e inmutable de operaciones críticas (véase TC026 y D10).
 */
class ImmutableLogEntryTest extends TestCase
{
    use InteractsWithDatabase;
    use MakesAuthenticatedRequests;
    use RefreshDatabase;

    public function test_it_records_the_critical_operation_with_its_identifier_and_payload(): void
    {
        $batchId = $this->createBatch();

        $entry = LogEntryModel::query()
            ->where('event_type', CreateBatch::EVENT_TYPE)
            ->first();

        $this->assertNotNull($entry, 'La operación crítica debe quedar registrada en el log.');
        $this->assertTrue(Uuid::isValid((string) $entry->getKey()));
        $this->assertSame(7, Uuid::version((string) $entry->getKey()));
        $this->assertSame($batchId, $entry->batch_id);
        $this->assertNotNull($entry->user_id);
        $this->assertSame(CreateBatch::EVENT_TYPE, $entry->event_type);
        $this->assertIsArray($entry->payload);
        $this->assertArrayHasKey('phase', $entry->payload);
        $this->assertArrayHasKey('status', $entry->payload);
        $this->assertArrayHasKey('reference_id', $entry->payload);
        $this->assertNotNull($entry->created_at);
    }

    public function test_it_records_the_write_request_with_the_user_that_performs_it(): void
    {
        $this->seedCampuses();
        $this->seedVaccineReferences();

        $operator = $this->actingAsUser('Operador de Producción');

        $response = $this->postJson('/api/v1/batches', [
            'reference_id' => $this->vaccineReferenceId('Vacuna conjugada intramuscular'),
        ]);

        $response->assertCreated();

        $batchId = (string) $response->json('id');

        $entry = LogEntryModel::query()
            ->where('event_type', AuditEventType::API_WRITE_REQUESTED)
            ->first();

        $this->assertNotNull($entry, 'La petición de escritura debe quedar registrada en el log.');
        $this->assertSame((string) $operator->getAuthIdentifier(), $entry->user_id);
        $this->assertSame($batchId, $entry->batch_id);
        $this->assertSame('POST', $entry->payload['method']);
        $this->assertSame('api/v1/batches', $entry->payload['path']);
        $this->assertSame(201, $entry->payload['status']);
    }

    public function test_the_log_entry_port_has_no_update_or_delete_path(): void
    {
        $port = new ReflectionClass(LogEntryRepository::class);
        $adapter = new ReflectionClass(EloquentLogEntryRepository::class);

        $this->assertSame(
            ['append'],
            $this->sortedMethodNames($port),
            'El puerto del log sólo admite el alta de entradas.',
        );

        $this->assertSame(
            ['append'],
            $this->sortedMethodNames($adapter),
            'El adaptador del log no expone actualización ni borrado.',
        );
    }

    public function test_it_rejects_the_modification_of_a_log_entry_and_keeps_it_intact(): void
    {
        $entryId = $this->logEntryId();

        $model = LogEntryModel::query()->findOrFail($entryId);

        try {
            $model->update(['event_type' => 'evento.alterado']);
            $this->fail('La aplicación no debe permitir modificar una entrada del log.');
        } catch (DomainException $exception) {
            $this->assertSame('immutable_log_entry', $exception->errorCode());
        }

        $this->assertLogEntryIsIntact($entryId);
    }

    public function test_it_rejects_the_deletion_of_a_log_entry_and_keeps_it_intact(): void
    {
        $entryId = $this->logEntryId();

        $model = LogEntryModel::query()->findOrFail($entryId);

        try {
            $model->delete();
            $this->fail('La aplicación no debe permitir eliminar una entrada del log.');
        } catch (DomainException $exception) {
            $this->assertSame('immutable_log_entry', $exception->errorCode());
        }

        $this->assertLogEntryIsIntact($entryId);
    }

    public function test_the_database_rejects_the_update_of_a_log_entry(): void
    {
        $entryId = $this->logEntryId();

        try {
            LogEntryModel::query()->where('id', $entryId)->update(['event_type' => 'evento.alterado']);
            $this->fail('La base de datos no debe permitir modificar una entrada del log.');
        } catch (QueryException $exception) {
            $this->assertTrue(
                $this->isImmutabilityRejection($exception),
                'El rechazo de la base de datos debe identificar la inmutabilidad del log.',
            );
        }

        $this->assertLogEntryIsIntact($entryId);
    }

    public function test_the_database_rejects_the_delete_of_a_log_entry(): void
    {
        $entryId = $this->logEntryId();

        try {
            LogEntryModel::query()->where('id', $entryId)->delete();
            $this->fail('La base de datos no debe permitir eliminar una entrada del log.');
        } catch (QueryException $exception) {
            $this->assertTrue(
                $this->isImmutabilityRejection($exception),
                'El rechazo de la base de datos debe identificar la inmutabilidad del log.',
            );
        }

        $this->assertLogEntryIsIntact($entryId);
    }

    public function test_it_rejects_a_payload_that_does_not_comply_with_the_event_schema(): void
    {
        $this->expectException(DomainException::class);

        LogEntry::record(AuditEventType::BATCH_CREATED, ['phase' => 'Fase de cultivo']);
    }

    public function test_it_rejects_an_unknown_event_type(): void
    {
        $this->expectException(DomainException::class);

        LogEntry::record('evento.desconocido', ['phase' => 'Fase de cultivo']);
    }

    /**
     * Identificador de la primera entrada registrada por una operación crítica.
     */
    private function logEntryId(): string
    {
        $this->createBatch();

        $entry = LogEntryModel::query()
            ->where('event_type', CreateBatch::EVENT_TYPE)
            ->first();

        $this->assertNotNull($entry);

        return (string) $entry->getKey();
    }

    /**
     * Comprueba que la entrada del log conserva su contenido original.
     */
    private function assertLogEntryIsIntact(string $entryId): void
    {
        $entry = LogEntryModel::query()->find($entryId);

        $this->assertNotNull($entry, 'La entrada del log no puede desaparecer.');
        $this->assertSame(CreateBatch::EVENT_TYPE, $entry->event_type);
        $this->assertNotNull($entry->batch_id);
        $this->assertNotNull($entry->created_at);
        $this->assertArrayHasKey('phase', $entry->payload);
    }

    /**
     * Indica si el rechazo de la base de datos corresponde a la inmutabilidad.
     */
    private function isImmutabilityRejection(QueryException $exception): bool
    {
        $code = (string) ($exception->getCode() ?: $exception->getPrevious()?->getCode());

        return $code === '45000' || str_contains($exception->getMessage(), 'log_entry_immutable');
    }

    /**
     * Crea un lote en la fase de cultivo y devuelve su identificador.
     */
    private function createBatch(): string
    {
        $this->seedCampuses();
        $this->seedVaccineReferences();
        $this->actingAsUser('Operador de Producción');

        $response = $this->postJson('/api/v1/batches', [
            'reference_id' => $this->vaccineReferenceId('Vacuna conjugada intramuscular'),
        ]);

        $response->assertCreated();

        return (string) $response->json('id');
    }

    /**
     * Nombres de los métodos públicos de una clase, ordenados.
     *
     * El constructor no es una vía de escritura del log y queda excluido.
     *
     * @return list<string>
     */
    private function sortedMethodNames(ReflectionClass $class): array
    {
        $names = array_map(
            fn (ReflectionMethod $method): string => $method->getName(),
            $class->getMethods(ReflectionMethod::IS_PUBLIC),
        );

        $names = array_values(array_filter(
            $names,
            fn (string $name): bool => $name !== '__construct',
        ));

        sort($names);

        return $names;
    }
}
