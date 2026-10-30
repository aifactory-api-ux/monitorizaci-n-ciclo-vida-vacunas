<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Operations\MeasureNonFunctionalThresholds;
use App\Domain\Operations\ValueObject\ThresholdMeasurement;
use Illuminate\Console\Command;

/**
 * Comando de medición de los umbrales no funcionales.
 *
 * Mide la carga del panel, el percentil 95 de la búsqueda avanzada, la
 * latencia de publicación de los eventos y la respuesta de las rutas
 * protegidas sin token. El comando termina con error cuando alguna de las
 * mediciones incumple su umbral, de modo que el despliegue no se promociona
 * con un incumplimiento (véase NFR001, NFR003, NFR006 y NFR008).
 */
class MeasureOperationalThresholds extends Command
{
    /**
     * Firma del comando.
     *
     * @var string
     */
    protected $signature = 'ops:thresholds {--json : Muestra el informe en formato JSON}';

    /**
     * Descripción del comando.
     *
     * @var string
     */
    protected $description = 'Mide los umbrales no funcionales exigidos y verifica su cumplimiento.';

    public function handle(MeasureNonFunctionalThresholds $measure): int
    {
        $report = $measure->execute();

        if ($this->option('json')) {
            $this->line((string) json_encode(
                $report->toArray(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ));
        } else {
            $this->table(
                ['Criterio', 'Indicador', 'Medido', 'Límite', 'Cumple'],
                array_map(
                    fn (ThresholdMeasurement $measurement): array => [
                        $measurement->criterion(),
                        $measurement->indicator(),
                        $measurement->unit() === ''
                            ? (string) $measurement->measured()
                            : sprintf('%.3f %s', $measurement->measured(), $measurement->unit()),
                        $measurement->unit() === ''
                            ? (string) $measurement->limit()
                            : sprintf('%.3f %s', $measurement->limit(), $measurement->unit()),
                        $measurement->isSatisfied() ? 'Sí' : 'No',
                    ],
                    $report->measurements(),
                ),
            );
        }

        foreach ($report->violations() as $violation) {
            $this->error(sprintf(
                'El criterio %s no cumple: %s ha medido %.3f %s y el límite es %.3f %s.',
                $violation->criterion(),
                $violation->indicator(),
                $violation->measured(),
                $violation->unit(),
                $violation->limit(),
                $violation->unit(),
            ));
        }

        if ($report->isCompliant()) {
            $this->info('Todos los umbrales no funcionales exigidos se cumplen.');

            return self::SUCCESS;
        }

        $this->error('Hay umbrales no funcionales que no se cumplen.');

        return self::FAILURE;
    }
}
