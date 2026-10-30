<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Application\Backup\RestoreCriticalData as RestoreCriticalDataService;
use App\Domain\Backup\Exception\BackupIntegrityException;
use App\Domain\Backup\Port\BackupArtifactStore;
use App\Domain\Backup\Port\CacheSnapshotStore;
use App\Domain\Backup\ValueObject\BackupManifest;
use App\Domain\User\ValueObject\UserRole;
use App\Domain\VaccineBatch\Entity\Campus;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;
use App\Domain\VaccineBatch\ValueObject\BatchStatus;
use App\Infrastructure\Backup\FilesystemBackupArtifactStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Doubles\FakeCacheSnapshotStore;
use Tests\Support\InteractsWithDatabase;
use Tests\Support\MakesAuthenticatedRequests;
use Tests\TestCase;

/**
 * Verificación del respaldo y de la restauración de los datos críticos.
 *
 * La prueba genera el respaldo sobre un almacén sembrado, cambia la conexión
 * por defecto a un almacén limpio y migrado, restaura el artefacto y comprueba
 * que los datos críticos quedan íntegros, que el almacén de claves recupera
 * su contenido y que la API vuelve a operar con normalidad. Comprueba
 * además que un artefacto alterado se rechaza antes de tocar el almacén de
 * destino (véase D19).
 */
class CriticalDataBackupRestoreTest extends TestCase
{
    use InteractsWithDatabase;
    use MakesAuthenticatedRequests;

    /**
     * Identificador del artefacto de respaldo de la prueba.
     */
    private const ARTIFACT_ID = 'respaldo-de-prueba';

    /**
     * Nombre de la conexión que representa el almacén limpio de destino.
     */
    private const TARGET_CONNECTION = 'respaldo_destino';

    /**
     * Directorio raíz de los artefactos de respaldo de la prueba.
     */
    private string $artifactRoot;

    /**
     * Fichero SQLite que representa el almacén limpio de destino.
     */
    private string $targetDatabase;

    /**
     * Doble en memoria del almacén de claves.
     */
    private FakeCacheSnapshotStore $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artifactRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'respaldo-prueba-'.Str::uuid()->toString();
        $this->targetDatabase = sys_get_temp_dir().DIRECTORY_SEPARATOR.'almacen-limpio-'.Str::uuid()->toString().'.sqlite';

        config(['backup.path' => $this->artifactRoot]);
        $this->app->instance(BackupArtifactStore::class, new FilesystemBackupArtifactStore($this->artifactRoot));

        $this->cache = new FakeCacheSnapshotStore;
        $this->app->instance(CacheSnapshotStore::class, $this->cache);

        $this->artisan('migrate')->assertSuccessful();
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->artifactRoot);

        if (is_file($this->targetDatabase)) {
            unlink($this->targetDatabase);
        }

        parent::tearDown();
    }

    public function test_it_restores_the_critical_data_on_a_clean_store_and_the_system_operates_again(): void
    {
        $batchId = $this->seedCriticalData();

        $this->artisan('backup:critical-data', ['--artifact' => self::ARTIFACT_ID])
            ->assertSuccessful();

        $this->assertArtifactIsIntact();

        $this->useCleanStore();

        $this->cache->replace([]);

        $this->artisan('restore:critical-data', ['--artifact' => self::ARTIFACT_ID])
            ->assertSuccessful();

        $this->assertCriticalDataIsRestored($batchId);

        $this->assertSame(2, $this->cache->count());
        $this->assertSame(
            'contenido-cacheado',
            $this->cache->get('busqueda:lote:'.$batchId)?->payload(),
        );

        $this->artisan('restore:critical-data', ['--artifact' => self::ARTIFACT_ID])
            ->assertSuccessful();

        $this->assertSame(1, DB::table('VACCINE_BATCH')->count());
        $this->assertSame(1, DB::table('LOG_ENTRY')->count());

        $this->actingAsUser(UserRole::PLANT_SUPERVISOR);

        $this->getJson('/api/v1/batches')
            ->assertOk()
            ->assertJsonPath('batches.0.id', $batchId)
            ->assertJsonPath('batches.0.phase', BatchPhase::CULTURE)
            ->assertJsonPath('batches.0.status', BatchStatus::IN_PROGRESS);
    }

    public function test_it_rejects_a_tampered_artifact_and_leaves_the_clean_store_untouched(): void
    {
        $batchId = $this->seedCriticalData();

        $this->artisan('backup:critical-data', ['--artifact' => self::ARTIFACT_ID])
            ->assertSuccessful();

        $databaseFile = $this->artifactRoot
            .DIRECTORY_SEPARATOR.self::ARTIFACT_ID
            .DIRECTORY_SEPARATOR.BackupManifest::DATABASE_FILE;

        file_put_contents($databaseFile, file_get_contents($databaseFile).' ');

        $this->useCleanStore();

        $cacheBefore = $this->cacheContents();

        $this->artisan('restore:critical-data', ['--artifact' => self::ARTIFACT_ID])
            ->assertFailed();

        $this->assertSame(0, DB::table('VACCINE_BATCH')->count());
        $this->assertSame(0, DB::table('LOG_ENTRY')->count());
        $this->assertSame($cacheBefore, $this->cacheContents());

        $violation = null;

        try {
            $this->app->make(RestoreCriticalDataService::class)->execute(self::ARTIFACT_ID);
        } catch (BackupIntegrityException $exception) {
            $violation = $exception;
        }

        $this->assertNotNull($violation, 'La restauración de un artefacto alterado debe rechazarse.');
        $this->assertSame(BackupIntegrityException::ERROR_CODE, $violation->errorCode());
    }

    /**
     * Siembra los datos críticos del almacén de origen.
     *
     * @return string Identificador del lote de referencia.
     */
    private function seedCriticalData(): string
    {
        $this->seedCampuses();
        $this->seedVaccineReferences();

        $user = $this->createUser(UserRole::PLANT_SUPERVISOR);

        $batchId = (string) Str::uuid();

        DB::table('VACCINE_BATCH')->insert([
            'id' => $batchId,
            'reference_id' => $this->vaccineReferenceId('Vacuna conjugada intramuscular'),
            'phase' => BatchPhase::CULTURE,
            'status' => BatchStatus::IN_PROGRESS,
            'current_campus_id' => $this->campusId(Campus::CULTURE),
            'created_at' => '2026-02-01 08:00:00',
            'updated_at' => '2026-02-01 08:00:00',
        ]);

        DB::table('LOG_ENTRY')->insert([
            'id' => (string) Str::uuid(),
            'batch_id' => $batchId,
            'user_id' => (string) $user->getKey(),
            'event_type' => 'batch.created',
            'payload' => json_encode(['lote' => $batchId], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'created_at' => '2026-02-01 08:00:01',
        ]);

        $this->cache->put('busqueda:lote:'.$batchId, 'contenido-cacheado', 120000);
        $this->cache->put('kpi:resumen', 'resumen-cacheado', 0);

        return $batchId;
    }

    /**
     * Comprueba que el artefacto generado está completo y es íntegro.
     */
    private function assertArtifactIsIntact(): void
    {
        $artifacts = $this->app->make(BackupArtifactStore::class);

        $this->assertTrue($artifacts->has(self::ARTIFACT_ID));
        $this->assertSame([self::ARTIFACT_ID], $artifacts->artifacts());

        $manifest = BackupManifest::fromArray(json_decode(
            $artifacts->get(self::ARTIFACT_ID, BackupManifest::MANIFEST_FILE),
            true,
            512,
            JSON_THROW_ON_ERROR,
        ));

        $this->assertSame(self::ARTIFACT_ID, $manifest->artifactId());
        $this->assertSame(1, $manifest->tableCounts()['VACCINE_BATCH'] ?? 0);
        $this->assertSame(1, $manifest->tableCounts()['LOG_ENTRY'] ?? 0);
        $this->assertSame(2, $manifest->cacheEntries());

        $this->assertSame(
            $manifest->databaseChecksum(),
            hash('sha256', $artifacts->get(self::ARTIFACT_ID, BackupManifest::DATABASE_FILE)),
        );

        $this->assertSame(
            $manifest->cacheChecksum(),
            hash('sha256', $artifacts->get(self::ARTIFACT_ID, BackupManifest::CACHE_FILE)),
        );
    }

    /**
     * Comprueba que los datos críticos se han restituido en el almacén limpio.
     */
    private function assertCriticalDataIsRestored(string $batchId): void
    {
        $this->assertSame(3, DB::table('CAMPUS')->count());
        $this->assertSame(3, DB::table('VACCINE_REFERENCE')->count());
        $this->assertSame(1, DB::table('APP_USER')->count());

        $batch = DB::table('VACCINE_BATCH')->first();

        $this->assertNotNull($batch);
        $this->assertSame($batchId, (string) $batch->id);
        $this->assertSame(BatchPhase::CULTURE, $batch->phase);
        $this->assertSame(BatchStatus::IN_PROGRESS, $batch->status);

        $entry = DB::table('LOG_ENTRY')->first();

        $this->assertNotNull($entry);
        $this->assertSame($batchId, (string) $entry->batch_id);
        $this->assertSame('batch.created', $entry->event_type);
        $this->assertSame(['lote' => $batchId], json_decode((string) $entry->payload, true));
    }

    /**
     * Sustituye la conexión por defecto por un almacén limpio y migrado.
     */
    private function useCleanStore(): void
    {
        touch($this->targetDatabase);

        config(['database.connections.'.self::TARGET_CONNECTION => [
            'driver' => 'sqlite',
            'database' => $this->targetDatabase,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);

        DB::purge(self::TARGET_CONNECTION);

        config(['database.default' => self::TARGET_CONNECTION]);

        $this->artisan('migrate')->assertSuccessful();
    }

    /**
     * Contenido del almacén de claves, indexado por el nombre de cada clave.
     *
     * @return array<string, string>
     */
    private function cacheContents(): array
    {
        $contents = [];

        foreach ($this->cache->entries() as $entry) {
            $contents[$entry->key()] = $entry->payload();
        }

        return $contents;
    }

    /**
     * Elimina recursivamente un directorio y su contenido.
     */
    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$entry;

            if (is_dir($path)) {
                $this->deleteDirectory($path);

                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
