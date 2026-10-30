<?php

declare(strict_types=1);

namespace App\Api\Http\Controllers\V1;

use App\Api\Http\Resources\LogEntryResource;
use App\Application\Audit\GetAuditTrail;
use App\Domain\Audit\Entity\LogEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints de consulta del historial inmutable de auditoría.
 *
 * El acceso está reservado al rol de Auditor / Administrador IT; la
 * comprobación del rol se aplica en la capa de aplicación (véase D9 y TC020).
 */
class AuditController
{
    public function __construct(
        private readonly GetAuditTrail $getAuditTrail,
    ) {}

    /**
     * Historial paginado de los eventos registrados.
     */
    public function index(Request $request): JsonResponse
    {
        $trail = $this->getAuditTrail->execute(
            $this->actorId($request),
            $this->positiveInt($request->query('page'), 1),
            $this->positiveInt($request->query('per_page'), 50),
        );

        return response()->json([
            'entries' => array_map(
                fn (LogEntry $entry): array => LogEntryResource::make($entry)->resolve(),
                $trail->entries(),
            ),
            'pagination' => [
                'page' => $trail->page(),
                'per_page' => $trail->perPage(),
                'total' => $trail->total(),
            ],
            'total' => $trail->total(),
        ]);
    }

    /**
     * Evento concreto del historial, identificado por su UUID.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $entry = $this->getAuditTrail->findById($this->actorId($request), $uuid);

        return response()->json(LogEntryResource::make($entry)->resolve());
    }

    /**
     * Identificador del usuario autenticado que realiza la consulta.
     */
    private function actorId(Request $request): ?string
    {
        $identifier = $request->user()?->getAuthIdentifier();

        return $identifier === null ? null : (string) $identifier;
    }

    /**
     * Convierte un parámetro de consulta en un entero positivo.
     */
    private function positiveInt(mixed $value, int $default): int
    {
        if (! is_string($value) && ! is_int($value)) {
            return $default;
        }

        $parsed = (int) $value;

        return $parsed > 0 ? $parsed : $default;
    }
}
