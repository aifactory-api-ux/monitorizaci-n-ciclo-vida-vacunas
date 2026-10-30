<?php

declare(strict_types=1);

namespace App\Infrastructure\Operations;

use App\Domain\Operations\Port\DeploymentInspector;
use App\Domain\Operations\ValueObject\PerimeterExposure;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\MiddlewareNameResolver;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * Inspector de rutas que recorre el enrutador de la aplicación.
 *
 * Enumera las rutas de la API y determina, a partir del middleware que
 * cada ruta tiene resuelto, si la ruta atiende peticiones sin credencial
 * (véase NFR012).
 */
final class RouterDeploymentInspector implements DeploymentInspector
{
    /**
     * Middleware que exige un token Bearer válido.
     */
    private const AUTHENTICATION_MIDDLEWARE = 'auth:api';

    /**
     * Prefijo de las rutas de la API.
     */
    private const API_PREFIX = 'api/v1';

    public function __construct(
        private readonly Router $router,
        Kernel $kernel,
    ) {
        // Los grupos de middleware los declara `bootstrap/app.php` y el
        // núcleo HTTP los sincroniza con el enrutador. La sincronización se
        // repite aquí porque el comando de verificación se ejecuta en consola,
        // donde el núcleo HTTP no se ha resuelto todavía.
        foreach ($kernel->getMiddlewareGroups() as $group => $middleware) {
            $this->router->middlewareGroup($group, $middleware);
        }
    }

    public function exposures(): array
    {
        $exposures = [];

        foreach ($this->router->getRoutes() as $route) {
            if (! $this->isApiRoute($route)) {
                continue;
            }

            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $exposures[] = PerimeterExposure::expose(
                    $method,
                    $route->uri(),
                    $this->requiresAuthentication($route),
                );
            }
        }

        return $exposures;
    }

    /**
     * Indica si la ruta pertenece a la API.
     */
    private function isApiRoute(Route $route): bool
    {
        return str_starts_with($route->uri(), self::API_PREFIX);
    }

    /**
     * Indica si la ruta exige un token Bearer válido.
     */
    private function requiresAuthentication(Route $route): bool
    {
        $middleware = $this->router->gatherRouteMiddleware($route);

        $authentication = MiddlewareNameResolver::resolve(
            self::AUTHENTICATION_MIDDLEWARE,
            $this->router->getMiddleware(),
            $this->router->getMiddlewareGroups(),
        );

        if (! is_string($authentication)) {
            return false;
        }

        return in_array($authentication, $middleware, true);
    }
}
