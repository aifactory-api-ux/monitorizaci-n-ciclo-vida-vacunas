<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Backup\RestoreCriticalData as RestoreCriticalDataService;
use App\Domain\Backup\Exception\BackupIntegrityException;
use App\Domain\Backup\Port\BackupArtifactStore;
use App\Domain\Shared\Exception\DomainException;
use Illuminate\Console\Command;

/**
 * Comando de restauración de los datos críticos.
 *
 * La restauración verifica el resumen de integridad de cada fichero del
 * artefacto antes de aplicar ningún dato. Un artefacto alterado o
 * incompleto se rechaza y el almacén de destino queda intacto (véase D19).
 */
class RestoreCriticalData extends Command
{
    /**
     * Firma del comando.
     *
     * @var string
     */
    protected $signature = 'restore:critical-data
                            {--artifact= : Identificador del artefacto de respaldo; si se omite, se restaura el más reciente}
                            {--list : Muestra los artefactos disponibles y termina}';

    /**
     * Descripción del comando.
     *
     * @var string
     */
    protected $description = 'Restaura los datos críticos de MySQL y Redis desde un artefacto de respaldo.';

    public function handle(RestoreCriticalDataService $restore, BackupArtifactStore $artifacts): int
    {
        $artifactId = $this->option('artifact');

        $identifier = is_string($artifactId) && $artifactId !== '' ? $artifactId : null;

        try {
            if ($this->option('list')) {
                return $this->listArtifacts($artifacts);
            }

            $report = $restore->execute($identifier);
        } catch (BackupIntegrityException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (DomainException $exception) {
            $this->error(sprintf('[%s] %s', $exception->errorCode(), $exception->getMessage()));

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Restauración del artefacto "%s" completada: %d filas y %d claves repuestas.',
            $report->artifactId(),
            $report->restoredRows(),
            $report->restoredKeys(),
        ));

        return self::SUCCESS;
    }

    /**
     * Muestra los artefactos de respaldo disponibles.
     */
    private function listArtifacts(BackupArtifactStore $artifacts): int
    {
        $available = $artifacts->artifacts();

        if ($available === []) {
            $this->warn('No hay ningún artefacto de respaldo disponible.');

            return self::SUCCESS;
        }

        foreach ($available as $artifactId) {
            $this->line($artifactId);
        }

        return self::SUCCESS;
    }
}
