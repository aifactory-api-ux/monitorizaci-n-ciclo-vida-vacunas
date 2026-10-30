<?php

declare(strict_types=1);

namespace App\Domain\Operations\Port;

use App\Domain\Operations\ValueObject\PerimeterExposure;

/**
 * Inspector de las rutas que el despliegue publica.
 *
 * Expone el conjunto de rutas de la API y si cada una exige un token Bearer
 * para atender las peticiones, de modo que la política del perímetro privado
 * pueda verificarse (véase NFR012).
 */
interface DeploymentInspector
{
    /**
     * Rutas publicadas por el despliegue.
     *
     * @return list<PerimeterExposure>
     */
    public function exposures(): array;
}
