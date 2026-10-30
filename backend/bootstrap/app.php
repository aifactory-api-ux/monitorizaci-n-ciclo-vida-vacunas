<?php

declare(strict_types=1);

use App\Api\Http\Middleware\AuditRequest;
use App\Api\Http\Middleware\EnsureUserRole;
use App\Domain\Shared\Exception\ConfigurationException;
use App\Domain\Shared\Exception\DomainException;
use App\Domain\Shared\Exception\InvalidCredentialsException;
use App\Domain\Shared\Exception\ResourceNotFoundException;
use App\Domain\Shared\Exception\UnauthorizedOperationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // La autorización del canal privado `batch-monitoring` exige un token
    // Bearer válido, igual que el resto de la API (véase D9).
    ->withBroadcasting(
        channels: __DIR__.'/../routes/channels.php',
        attributes: ['middleware' => ['auth:api']],
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->throttleApi('api');
        // La aplicación es una API sin formulario de acceso: los invitados
        // reciben un 401 con cuerpo JSON en lugar de una redirección.
        $middleware->redirectGuestsTo(fn (): ?string => null);
        // El grupo `api` protege por defecto con `auth:api`: las rutas de
        // datos exigen un token Bearer JWT emitido por Passport (véase D9).
        // Las rutas públicas (salud y emisión de token) lo excluyen de forma
        // explícita en `routes/api.php`.
        $middleware->api(append: ['auth:api']);

        // La autorización por rol y la auditoría de las peticiones de
        // escritura se aplican como middleware de ruta (véase D9 y D10).
        $middleware->alias([
            'role' => EnsureUserRole::class,
            'audit.request' => AuditRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // La API y la autenticación del canal de tiempo real responden siempre
        // en JSON, incluso cuando el cliente no envía la cabecera `Accept`.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e): bool => $request->is('api/*', 'broadcasting/*') || $request->expectsJson()
        );

        // Un fallo de datos maestros no es un error del solicitante, pero
        // tampoco puede resolverse en silencio.
        $exceptions->render(function (ConfigurationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'error_code' => $exception->errorCode(),
            ], 500);
        });

        // Un recurso inexistente responde 404.
        $exceptions->render(function (ResourceNotFoundException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'error_code' => $exception->errorCode(),
            ], 404);
        });

        // Una operación prohibida al actor responde 403 con el motivo. Se
        // registra antes que el de `DomainException` porque es su subclase y
        // el primer renderer registrado es el que se aplica.
        $exceptions->render(function (UnauthorizedOperationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'error_code' => $exception->errorCode(),
            ], 403);
        });

        // Las credenciales de acceso no válidas responden 401, igual que una
        // petición sin cabecera `Authorization` o con un token caducado. Se
        // registra antes que el de `DomainException` porque es su subclase.
        $exceptions->render(function (InvalidCredentialsException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'error_code' => $exception->errorCode(),
            ], 401);
        });

        // Las reglas de negocio del dominio se traducen a la respuesta
        // documentada: 422 cuando el solicitante incumple una regla (véase D4).
        $exceptions->render(function (DomainException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'error_code' => $exception->errorCode(),
            ], 422);
        });

        // El intento de modificar o borrar una entrada del log de auditoría
        // se rechaza en la propia base de datos, que aborta la operación con
        // SQLSTATE 45000 (véase D10). La respuesta documenta el rechazo y
        // nunca es un 500.
        $exceptions->render(function (QueryException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $code = (string) ($exception->getCode() ?: $exception->getPrevious()?->getCode());

            $isImmutable = $code === '45000'
                || str_contains($exception->getMessage(), 'log_entry_immutable');

            if (! $isImmutable) {
                return null;
            }

            return response()->json([
                'message' => 'Las entradas del log de auditoría son inmutables: no admiten modificaciones ni eliminaciones.',
                'error_code' => 'immutable_log_entry',
            ], 422);
        });

        // Las violaciones de integridad de la base de datos responden con el
        // error documentado y nunca con un 500.
        $exceptions->render(function (QueryException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $states = ['23000', '23505', '23503'];

            // El SQLSTATE viaja en la excepción del driver, no en la de
            // consulta, que se crea con el código 0.
            $code = (string) ($exception->getCode() ?: $exception->getPrevious()?->getCode());

            $violatesIntegrity = in_array($code, $states, true)
                || preg_match('/SQLSTATE\[('.implode('|', $states).')\]/', $exception->getMessage()) === 1;

            if (! $violatesIntegrity) {
                return null;
            }

            return response()->json([
                'message' => 'La operación infringe una restricción de integridad de los datos.',
                'error_code' => 'integrity_constraint_violation',
            ], 422);
        });
    })->create();
