<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Lims\ImportLimsResults;
use Illuminate\Console\Command;

/**
 * Comando de importación programada de los resultados de LIMS Labware.
 *
 * La importación es periódica y no requiere intervención manual: el comando
 * lee las cargas disponibles y persiste las que son consistentes.
 */
class SyncLimsResults extends Command
{
    /**
     * Firma del comando.
     *
     * @var string
     */
    protected $signature = 'lims:sync {--batch= : Identificador UUID del lote a sincronizar}';

    /**
     * Descripción del comando.
     *
     * @var string
     */
    protected $description = 'Importa los resultados microbiológicos de LIMS Labware.';

    public function handle(ImportLimsResults $importLimsResults): int
    {
        if (! config('lims.enabled')) {
            $this->error('La integración con LIMS Labware está deshabilitada: actívela con LIMS_ENABLED para sincronizar resultados.');

            return self::FAILURE;
        }

        $batchId = $this->option('batch');

        $summary = $importLimsResults->execute(is_string($batchId) && $batchId !== '' ? $batchId : null);

        $this->info(sprintf(
            'Lotes importados: %d. Resultados registrados: %d. Cargas descartadas: %d.',
            $summary->importedBatches(),
            $summary->importedResults(),
            $summary->rejectedPayloads(),
        ));

        foreach ($summary->rejections() as $rejection) {
            $this->warn(sprintf('[%s] %s', $rejection['error_code'], $rejection['error_detail']));
        }

        return self::SUCCESS;
    }
}
