<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PDOException;
use Tests\TestCase;

/**
 * Traducción de los errores del dominio y de la base de datos a la respuesta
 * documentada de la API.
 */
class ErrorMappingTest extends TestCase
{
    public function test_an_integrity_violation_answers_with_422(): void
    {
        $previous = new PDOException(
            'SQLSTATE[23000]: Integrity constraint violation: 19 FOREIGN KEY constraint failed',
            23000,
        );
        $exception = new QueryException('sqlite', 'insert into "VACCINE_BATCH" ...', [], $previous);

        $response = $this->render($exception);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('integrity_constraint_violation', $response->getData(true)['error_code']);
    }

    public function test_a_query_failure_without_integrity_violation_keeps_the_internal_error(): void
    {
        $previous = new PDOException('SQLSTATE[HY000]: General error: 1 no such table');
        $exception = new QueryException('sqlite', 'select * from "AUSENTE"', [], $previous);

        $response = $this->render($exception);

        $this->assertSame(500, $response->getStatusCode());
    }

    /**
     * Renderiza una excepción con el gestor de la aplicación.
     */
    private function render(\Throwable $exception): JsonResponse
    {
        $request = Request::create('/api/v1/batches', 'POST');

        /** @var JsonResponse $response */
        $response = app(ExceptionHandler::class)->render($request, $exception);

        return $response;
    }
}
