<?php

declare(strict_types=1);

namespace App\Domain\Operations\Service;

use App\Domain\Operations\ValueObject\PerimeterExposure;
use App\Domain\Operations\ValueObject\PerimeterVerdict;

/**
 * Política del perímetro privado de MSD.
 *
 * El sistema se despliega dentro del perímetro de MSD, en On-Premise o en la
 * nube privada: sólo el sondeo de disponibilidad y la emisión del token
 * pueden atender peticiones sin credencial. Cualquier otra ruta expuesta sin
 * autenticación es una violación que impide dar por válido el despliegue
 * (véase NFR012).
 */
final class PrivatePerimeterPolicy
{
    /**
     * Rutas que pueden atender peticiones sin credencial dentro del
     * perímetro privado.
     *
     * @var list<string>
     */
    public const ALLOWED_PUBLIC_ROUTES = [
        'api/v1/health',
        'api/v1/auth/token',
    ];

    /**
     * Evalúa las exposiciones del despliegue.
     *
     * @param  list<PerimeterExposure>  $exposures
     */
    public function evaluate(array $exposures): PerimeterVerdict
    {
        $violations = [];

        foreach ($exposures as $exposure) {
            if (! $exposure->isPublic() || $this->isAllowed($exposure)) {
                continue;
            }

            $violations[] = sprintf(
                'La ruta «%s» atiende peticiones sin token Bearer y queda fuera del perímetro privado.',
                $exposure->describe(),
            );
        }

        return new PerimeterVerdict($violations);
    }

    /**
     * Indica si la exposición está admitida dentro del perímetro.
     */
    private function isAllowed(PerimeterExposure $exposure): bool
    {
        return in_array(ltrim($exposure->uri(), '/'), self::ALLOWED_PUBLIC_ROUTES, true);
    }
}
