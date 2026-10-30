<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Security\Port\SecurityStageScanner;
use App\Domain\Security\ValueObject\SecurityStageResult;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Analizador de seguridad que ejecuta un comando del despliegue.
 *
 * La etapa de seguridad del pipeline ejecuta el análisis de las
 * vulnerabilidades de las dependencias. El analizador ejecuta el comando
 * declarado para la etapa y traduce su resultado: el código de salida cero
 * significa que la etapa ha terminado sin detectar vulnerabilidades y
 * cualquier otro código bloquea la promoción (véase D20 y NFR011).
 */
final class ProcessSecurityStageScanner implements SecurityStageScanner
{
    /**
     * Número de líneas de la salida que se conservan en el resumen.
     */
    private const SUMMARY_LINES = 5;

    /**
     * @param  string  $stage  Nombre de la etapa.
     * @param  string  $command  Comando que ejecuta el análisis.
     * @param  int  $timeoutSeconds  Tiempo máximo de ejecución del análisis.
     */
    public function __construct(
        private readonly string $stage,
        private readonly string $command,
        private readonly int $timeoutSeconds = 300,
    ) {}

    public function scan(): SecurityStageResult
    {
        if (trim($this->command) === '') {
            return SecurityStageResult::failed(
                $this->stage,
                'No hay ningún comando de análisis configurado para la etapa.',
            );
        }

        try {
            $process = Process::fromShellCommandline($this->command);
            $process->setTimeout($this->timeoutSeconds);
            $process->run();

            $output = $process->getOutput().$process->getErrorOutput();

            if ($process->getExitCode() === 0) {
                return SecurityStageResult::passed($this->stage, $this->summary($output));
            }

            return SecurityStageResult::failed(
                $this->stage,
                $this->summary($output),
                $this->findings($output),
            );
        } catch (ProcessTimedOutException) {
            return SecurityStageResult::failed(
                $this->stage,
                sprintf('El análisis ha superado el tiempo máximo de %d segundos.', $this->timeoutSeconds),
            );
        } catch (Throwable $exception) {
            return SecurityStageResult::failed(
                $this->stage,
                sprintf('No se ha podido ejecutar el análisis: %s', $exception->getMessage()),
            );
        }
    }

    /**
     * Resumen de las últimas líneas de la salida del análisis.
     */
    private function summary(string $output): string
    {
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\R/', $output) ?: []),
            static fn (string $line): bool => $line !== '',
        ));

        if ($lines === []) {
            return 'El análisis no ha producido salida.';
        }

        return implode(' | ', array_slice($lines, -self::SUMMARY_LINES));
    }

    /**
     * Identificadores de las vulnerabilidades detectadas en la salida.
     *
     * @return list<string>
     */
    private function findings(string $output): array
    {
        preg_match_all('/\b(CVE-\d{4}-\d+)\b/i', $output, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }
}
