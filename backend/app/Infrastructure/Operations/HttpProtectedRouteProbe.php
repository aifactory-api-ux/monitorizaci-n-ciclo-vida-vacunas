<?php

declare(strict_types=1);

namespace App\Infrastructure\Operations;

use App\Domain\Operations\Port\ProtectedRouteProbe;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Sonda de una ruta de datos protegida por el middleware de autenticación.
 *
 * Emite la petición por el ciclo completo de HTTP sin cabecera
 * `Authorization` y devuelve el código de estado con el que responde la
 * API, que debe ser 401 (véase D9 y NFR003).
 */
final class HttpProtectedRouteProbe implements ProtectedRouteProbe
{
    public function __construct(
        private readonly Kernel $kernel,
        private readonly string $uri,
    ) {}

    public function statusCode(): int
    {
        // La sonda observa la respuesta a una petición sin credencial: cualquier
        // usuario autenticado en el proceso se descarta antes de emitirla.
        Auth::forgetGuards();

        $request = Request::create($this->uri, 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        return $this->kernel->handle($request)->getStatusCode();
    }
}
