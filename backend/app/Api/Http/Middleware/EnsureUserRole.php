<?php

declare(strict_types=1);

namespace App\Api\Http\Middleware;

use App\Application\Authorization\ActorRoleResolver;
use App\Application\Authorization\OperationPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de autorización por rol.
 *
 * Exige que el rol del actor autenticado admita todas las operaciones
 * declaradas en la ruta. La matriz de permisos vive en la capa de
 * aplicación, que es la única fuente de verdad (véase D9).
 */
class EnsureUserRole
{
    public function __construct(
        private readonly ActorRoleResolver $roles,
        private readonly OperationPolicy $policy,
    ) {}

    /**
     * Comprueba el perfil del actor antes de atender la petición.
     */
    public function handle(Request $request, Closure $next, string ...$operations): Response
    {
        $actor = $request->user();
        $role = $this->roles->resolve($actor === null ? null : (string) $actor->getAuthIdentifier());

        foreach ($operations as $operation) {
            $this->policy->assertAllowed($role, $operation);
        }

        return $next($request);
    }
}
