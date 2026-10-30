<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Alerts\EvaluateBatchMonitoring;
use App\Domain\Alert\Entity\Alert;
use App\Domain\Shared\Exception\ResourceNotFoundException;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Comando de evaluación de la monitorización de los lotes.
 *
 * El comando recorre las lecturas que publica la monitorización de la planta y
 * evalúa cada una con los detectores de desviación térmica y de anomalía de
 * formulación Sphereon. Las alertas que procedan se generan, se persisten y se
 * notifican de forma automática: no existe ninguna petición de usuario que
 * ordene revisar las lecturas (véase TC005 y TC023).
 */
class MonitorBatchAlerts extends Command
{
    /**
     * Firma del comando.
     *
     * @var string
     */
    protected $signature = 'alerts:monitor {--feed= : Ruta del fichero de lecturas de la monitorización}';

    /**
     * Descripción del comando.
     *
     * @var string
     */
    protected $description = 'Evalúa las lecturas de la monitorización y genera las alertas operativas de prioridad máxima.';

    public function handle(EvaluateBatchMonitoring $evaluateBatchMonitoring): int
    {
        $path = $this->feedPath();

        if ($path === null) {
            $this->error('No hay ninguna ruta de lecturas configurada: declara MONITORING_FEED_PATH o usa la opción --feed.');

            return self::FAILURE;
        }

        $readings = $this->readings($path);

        if ($readings === null) {
            return self::FAILURE;
        }

        $evaluated = 0;
        $raised = 0;

        foreach ($readings as $reading) {
            if (! is_array($reading)) {
                $this->warn('Se ha omitido una lectura de monitorización con un formato no válido.');

                continue;
            }

            $batchId = $reading['batch_id'] ?? null;

            if (! is_string($batchId) || trim($batchId) === '') {
                $this->warn('Se ha omitido una lectura de monitorización sin identificador de lote.');

                continue;
            }

            $alerts = $this->evaluate($evaluateBatchMonitoring, $batchId, $reading);

            if ($alerts === null) {
                continue;
            }

            $evaluated++;
            $raised += count($alerts);
        }

        $this->info(sprintf('Lecturas evaluadas: %d. Alertas generadas: %d.', $evaluated, $raised));

        return self::SUCCESS;
    }

    /**
     * Ruta del fichero de lecturas de la monitorización.
     */
    private function feedPath(): ?string
    {
        $option = $this->option('feed');
        $path = is_string($option) && $option !== '' ? $option : config('monitoring.feed.path');

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        return $path;
    }

    /**
     * Lee las lecturas del fichero de la monitorización.
     *
     * @return list<mixed>|null Las lecturas leídas o null si el fichero no se puede leer.
     */
    private function readings(string $path): ?array
    {
        if (! is_file($path) || ! is_readable($path)) {
            $this->error(sprintf('No se puede leer el fichero de lecturas de la monitorización «%s».', $path));

            return null;
        }

        $contents = file_get_contents($path);
        $readings = json_decode($contents === false ? '' : $contents, true);

        if (! is_array($readings)) {
            $this->error(sprintf('El fichero de lecturas de la monitorización «%s» no contiene un JSON válido.', $path));

            return null;
        }

        return array_values($readings);
    }

    /**
     * Evalúa una lectura de la monitorización.
     *
     * @param  array<string, mixed>  $reading
     * @return list<Alert>|null Las alertas generadas o null si el lote no existe.
     */
    private function evaluate(EvaluateBatchMonitoring $evaluateBatchMonitoring, string $batchId, array $reading): ?array
    {
        try {
            return $evaluateBatchMonitoring->execute(
                $batchId,
                isset($reading['thermal_deviation']) ? (float) $reading['thermal_deviation'] : null,
                isset($reading['sphereon_anomaly']) ? (float) $reading['sphereon_anomaly'] : null,
                $this->detectedAt($reading['detected_at'] ?? null),
            );
        } catch (ResourceNotFoundException $exception) {
            $this->warn($exception->getMessage());

            return null;
        }
    }

    /**
     * Fecha y hora de la detección indicada en la lectura.
     */
    private function detectedAt(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            $this->warn(sprintf('Se ha omitido la fecha de detección «%s»: no es una fecha válida.', $value));

            return null;
        }
    }
}
