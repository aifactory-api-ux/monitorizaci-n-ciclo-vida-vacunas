<?php

declare(strict_types=1);

namespace App\Application\Operations;

use App\Domain\Operations\Port\DeploymentInspector;
use App\Domain\Operations\Service\PrivatePerimeterPolicy;
use App\Domain\Operations\ValueObject\PerimeterExposure;
use App\Domain\Operations\ValueObject\PerimeterVerdict;

/**
 * Caso de uso de verificación del perímetro privado del despliegue.
 *
 * Comprueba que todas las rutas de datos de la API exigen un token Bearer
 * válido: dentro del perímetro de MSD, en On-Premise o en la nube privada,
 * sólo el sondeo de disponibilidad y la emisión del token pueden atender
 * peticiones sin credencial (véase NFR012).
 */
final class VerifyPrivatePerimeter
{
    public function __construct(
        private readonly DeploymentInspector $inspector,
        private readonly PrivatePerimeterPolicy $policy,
    ) {}

    /**
     * Evalúa la exposición del despliegue.
     */
    public function execute(): PerimeterVerdict
    {
        return $this->policy->evaluate($this->inspector->exposures());
    }

    /**
     * Rutas publicadas por el despliegue.
     *
     * @return list<PerimeterExposure>
     */
    public function exposures(): array
    {
        return $this->inspector->exposures();
    }
}
