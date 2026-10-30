<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Backup\BackupCriticalData as BackupCriticalDataService;
use App\Domain\Backup\Exception\BackupIntegrityException;
use App\Domain\Backup\ValueObject\BackupManifest;
use App\Domain\Shared\Exception\DomainException;
use Illuminate\Console\Command;

/**
 * Comando de generación del respaldo de los datos críticos.
 *
 * El respaldo recoge las filas de las tablas críticas de MySQL y el estado
 * del almacén de claves de Redis, y los deposita en un artefacto portable
 * con su manifiesto y sus resúmenes de integridad. El comando se ejecuta de
 * forma programada desde el despliegue (véase D19).
 */
class BackupCriticalData extends Command
{
    /**
     * Firma del comando.
     *
     * @var string
     */
    protected $signature = 'backup:critical-data
                            {--artifact= : Identificador del artefacto de respaldo; si se omite, se genera uno ordenable en el tiempo}';

    /**
     * Descripción del comando.
     *
     * @var string
     */
    protected $description = 'Genera el respaldo de los datos críticos de MySQL y Redis.';

    public function handle(BackupCriticalDataService $backup): int
    {
        $artifactId = $this->option('artifact');

        try {
            $manifest = $backup->execute(is_string($artifactId) && $artifactId !== '' ? $artifactId : null);
        } catch (BackupIntegrityException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (DomainException $exception) {
            $this->error(sprintf('[%s] %s', $exception->errorCode(), $exception->getMessage()));

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Respaldo generado con el identificador "%s" en "%s".',
            $manifest->artifactId(),
            $this->artifactPath($manifest->artifactId()),
        ));

        foreach ($manifest->tableCounts() as $table => $count) {
            $this->line(sprintf('  %s: %d filas.', $table, $count));
        }

        $this->line(sprintf('  %s: %d claves.', BackupManifest::CACHE_FILE, $manifest->cacheEntries()));

        return self::SUCCESS;
    }

    /**
     * Ruta del artefacto generado.
     */
    private function artifactPath(string $artifactId): string
    {
        return rtrim((string) $this->laravel['config']->get('backup.path'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.$artifactId;
    }
}
