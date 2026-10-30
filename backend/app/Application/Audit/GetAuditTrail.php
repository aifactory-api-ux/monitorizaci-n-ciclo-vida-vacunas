<?php

declare(strict_types=1);

namespace App\Application\Audit;

use App\Domain\Audit\Entity\LogEntry;
use App\Domain\Audit\Repository\AuditTrailRepository;
use App\Domain\Shared\Exception\ResourceNotFoundException;
use App\Domain\Shared\Exception\UnauthorizedOperationException;
use App\Domain\User\Entity\AppUser;
use App\Domain\User\Repository\AppUserRepository;

/**
 * Caso de uso de consulta del historial completo e inmutable de auditoría.
 *
 * El historial es auditable por el rol de Auditor / Administrador IT, que
 * obtiene la totalidad de los eventos con sus identificadores y su carga
 * útil (TC020). La comprobación del rol se aplica en esta capa, que es la
 * única fuente de verdad de permisos (D9).
 */
final class GetAuditTrail
{
    /**
     * Número máximo de entradas devueltas en una página.
     */
    public const MAX_PER_PAGE = 200;

    public function __construct(
        private readonly AuditTrailRepository $auditTrail,
        private readonly AppUserRepository $users,
    ) {}

    /**
     * Página del historial de auditoría solicitada por el actor.
     *
     * @throws UnauthorizedOperationException Si el actor no tiene el rol de auditoría.
     */
    public function execute(?string $actorId, int $page = 1, int $perPage = 50): AuditTrailPage
    {
        $this->assertAuditor($actorId);

        $page = max(1, $page);
        $perPage = min(self::MAX_PER_PAGE, max(1, $perPage));

        return new AuditTrailPage(
            $this->auditTrail->findAll($perPage, ($page - 1) * $perPage),
            $this->auditTrail->count(),
            $page,
            $perPage,
        );
    }

    /**
     * Evento concreto del historial, identificado por su UUID.
     *
     * @throws UnauthorizedOperationException Si el actor no tiene el rol de auditoría.
     * @throws ResourceNotFoundException Si no existe ningún evento con ese identificador.
     */
    public function findById(?string $actorId, string $eventId): LogEntry
    {
        $this->assertAuditor($actorId);

        $entry = $this->auditTrail->findById($eventId);

        if ($entry === null) {
            throw ResourceNotFoundException::auditEntry($eventId);
        }

        return $entry;
    }

    /**
     * Exige que el actor tenga el rol de Auditor / Administrador IT.
     *
     * @throws UnauthorizedOperationException Si el actor no tiene el rol de auditoría.
     */
    private function assertAuditor(?string $actorId): void
    {
        if ($actorId === null || $actorId === '') {
            throw UnauthorizedOperationException::auditTrailAccess();
        }

        $actor = $this->users->findById($actorId);

        if (! $actor instanceof AppUser || ! $actor->role()->isAuditor()) {
            throw UnauthorizedOperationException::auditTrailAccess();
        }
    }
}
