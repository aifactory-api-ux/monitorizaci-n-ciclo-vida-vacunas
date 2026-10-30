<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Security\RunSecurityStages;
use App\Domain\Security\ValueObject\SecurityStageResult;
use Illuminate\Console\Command;

/**
 * Comando de la puerta de seguridad de la promoción.
 *
 * Ejecuta las etapas de análisis de seguridad declaradas por el despliegue y
 * bloquea la promoción cuando alguna de ellas detecta vulnerabilidades o no
 * llega a ejecutarse (véase D20 y NFR011).
 */
class VerifySecurityGate extends Command
{
    /**
     * Firma del comando.
     *
     * @var string
     */
    protected $signature = 'security:gate {--json : Muestra el veredicto en formato JSON}';

    /**
     * Descripción del comando.
     *
     * @var string
     */
    protected $description = 'Ejecuta las etapas de análisis de seguridad y bloquea la promoción si detectan vulnerabilidades.';

    public function handle(RunSecurityStages $stages): int
    {
        $verdict = $stages->execute();

        if ($this->option('json')) {
            $this->line((string) json_encode(
                $verdict->toArray(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ));
        } else {
            $this->table(
                ['Etapa', 'Resultado', 'Resumen', 'Vulnerabilidades'],
                array_map(
                    fn (SecurityStageResult $result): array => [
                        $result->stage(),
                        $result->hasPassed() ? 'Correcta' : 'Fallida',
                        $result->summary(),
                        implode(', ', $result->findings()) ?: '-',
                    ],
                    $verdict->results(),
                ),
            );
        }

        if ($verdict->allowsPromotion()) {
            $this->info('Las etapas de seguridad son correctas: la promoción está permitida.');

            return self::SUCCESS;
        }

        $this->error(sprintf(
            'Las etapas de seguridad bloquean la promoción: %s.',
            implode(', ', $verdict->blockedStages()),
        ));

        return self::FAILURE;
    }
}
