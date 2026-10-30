<?php

declare(strict_types=1);

namespace App\Api\Http\Middleware;

use App\Domain\Audit\Service\AuditEventType;
use App\Domain\Audit\Service\StructuredAuditLogger;
use App\Domain\Shared\Exception\ConfigurationException;
use App\Domain\Shared\Exception\DomainException;
use App\Domain\Shared\Exception\ResourceNotFoundException;
use App\Domain\Shared\Exception\UnauthorizedOperationException;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Middleware de auditoría de las peticiones de escritura.
 *
 * Toda petición de escritura que llega a la API queda registrada en el log
 * estructurado e inmutable con el usuario que la ejecuta, el método, la ruta
 * atendida y el estado devuelto, incluidos los intentos denegados (véase D10
 * y TC026).
 */
class AuditRequest
{
    /**
     * Métodos HTTP que modifican datos.
     */
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(
        private readonly StructuredAuditLogger $logger,
    ) {}

    /**
     * Atiende la petición y registra su ejecución en el log de auditoría.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $status = Response::HTTP_INTERNAL_SERVER_ERROR;
        $response = null;

        try {
            $response = $next($request);
            $status = $response->getStatusCode();

            return $response;
        } catch (Throwable $exception) {
            $status = $this->statusFor($exception);

            throw $exception;
        } finally {
            $this->record($request, $status, $response);
        }
    }

    /**
     * Registra la petición de escritura en el log de auditoría.
     */
    private function record(Request $request, int $status, ?Response $response): void
    {
        if (! in_array($request->method(), self::WRITE_METHODS, true)) {
            return;
        }

        $actor = $request->user();

        $this->logger->record(
            AuditEventType::API_WRITE_REQUESTED,
            [
                'method' => $request->method(),
                'path' => $request->path(),
                'status' => $status,
            ],
            $this->batchId($request, $status, $response),
            $actor === null ? null : (string) $actor->getAuthIdentifier(),
        );
    }

    /**
     * Lote al que pertenece la escritura, si la petición lo identifica.
     *
     * Sólo se asocia el lote cuando la escritura ha sido atendida con éxito:
     * en caso contrario el lote puede no existir y la referencia del log
     * apuntaría a un registro inexistente.
     */
    private function batchId(Request $request, int $status, ?Response $response): ?string
    {
        if ($status < 200 || $status >= 300) {
            return null;
        }

        $batchId = $request->route('batchId');

        if (is_string($batchId) && $batchId !== '') {
            return $batchId;
        }

        return $this->createdBatchId($request, $response);
    }

    /**
     * Identificador del lote creado por la petición, si la respuesta lo expone.
     */
    private function createdBatchId(Request $request, ?Response $response): ?string
    {
        if ($request->method() !== 'POST' || $request->routeIs('api.v1.batches.store') === false) {
            return null;
        }

        if ($response === null) {
            return null;
        }

        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return null;
        }

        $payload = json_decode($content, true);

        if (! is_array($payload)) {
            return null;
        }

        $id = $payload['id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * Código de estado HTTP que corresponde a la excepción produite.
     */
    private function statusFor(Throwable $exception): int
    {
        return match (true) {
            $exception instanceof AuthenticationException => Response::HTTP_UNAUTHORIZED,
            $exception instanceof UnauthorizedOperationException => Response::HTTP_FORBIDDEN,
            $exception instanceof ResourceNotFoundException => Response::HTTP_NOT_FOUND,
            $exception instanceof ValidationException => Response::HTTP_UNPROCESSABLE_ENTITY,
            $exception instanceof ConfigurationException => Response::HTTP_INTERNAL_SERVER_ERROR,
            $exception instanceof DomainException => Response::HTTP_UNPROCESSABLE_ENTITY,
            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };
    }
}
