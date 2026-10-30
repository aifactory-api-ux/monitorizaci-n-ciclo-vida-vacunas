<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Operations\VerifyPrivatePerimeter as VerifyPrivatePerimeterDeployment;
use App\Domain\Operations\ValueObject\PerimeterExposure;
use Illuminate\Console\Command;

/**
 * Comando de verificación del perímetro privado del despliegue.
 *
 * Enumera las rutas que publica la API y comprueba que todas las rutas de
 * datos exigen un token Bearer válido: dentro del perímetro de MSD, en
 * On-Premise o en la nube privada, sólo el sondeo de disponibilidad y la
 * emisión del token pueden atender peticiones sin credencial (véase NFR012).
 */
class VerifyPrivatePerimeter extends Command
{
    /**
     * Firma del comando.
     *
     * @var string
     */
    protected $signature = 'ops:verify-perimeter {--json : Muestra el veredicto en formato JSON}';

    /**
     * Descripción del comando.
     *
     * @var string
     */
    protected $description = 'Verifica que la API sólo expone rutas públicas de sondeo y de emisión de token.';

    public function handle(VerifyPrivatePerimeterDeployment $perimeter): int
    {
        $verdict = $perimeter->execute();

        if ($this->option('json')) {
            $this->line((string) json_encode(
                $verdict->toArray(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ));
        } else {
            $this->table(
                ['Ruta', 'Autenticación'],
                array_map(
                    fn (PerimeterExposure $exposure): array => [
                        $exposure->describe(),
                        $exposure->requiresAuthentication() ? 'Exige token Bearer' : 'Pública',
                    ],
                    $perimeter->exposures(),
                ),
            );
        }

        if ($verdict->isCompliant()) {
            $this->info('El despliegue respeta el perímetro privado de MSD.');

            return self::SUCCESS;
        }

        foreach ($verdict->violations() as $violation) {
            $this->error($violation);
        }

        return self::FAILURE;
    }
}
