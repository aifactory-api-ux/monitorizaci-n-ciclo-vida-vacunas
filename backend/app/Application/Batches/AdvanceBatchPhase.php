<?php

declare(strict_types=1);

namespace App\Application\Batches;

use App\Application\Lims\EvaluateGatingRelease;
use App\Domain\Audit\Service\StructuredAuditLogger;
use App\Domain\Search\Port\BatchSearchCache;
use App\Domain\Shared\Event\DomainEventRecorder;
use App\Domain\Shared\Exception\ConfigurationException;
use App\Domain\Shared\Exception\DomainException;
use App\Domain\Shared\Exception\ResourceNotFoundException;
use App\Domain\VaccineBatch\Entity\PhaseTransition;
use App\Domain\VaccineBatch\Entity\VaccineBatch;
use App\Domain\VaccineBatch\Event\BatchPhaseAdvanced;
use App\Domain\VaccineBatch\Repository\CampusRepository;
use App\Domain\VaccineBatch\Repository\PhaseTransitionRepository;
use App\Domain\VaccineBatch\Repository\VaccineBatchRepository;
use App\Domain\VaccineBatch\Service\GatingPolicy;
use App\Domain\VaccineBatch\Service\PhaseTransitionPolicy;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;
use DateTimeImmutable;

/**
 * Caso de uso de avance secuencial de fase.
 *
 * La regla de secuencia vive en el dominio (véase D4): el caso de uso sólo
 * la delega y no muta el lote cuando la transición no está permitida
 * (véase TC012). El avance a la fase logística exige la aprobación
 * microbiológica de LIMS Labware: sin ella el lote queda bloqueado por gating
 * y la operación responde 422 sin avanzar de fase (véase D5 y TC009).
 */
final class AdvanceBatchPhase
{
    /**
     * Tipo de evento registrado en el log estructurado.
     */
    public const EVENT_TYPE = 'batch.phase.advanced';

    /**
     * Tipo de evento registrado cuando el gating bloquea el avance.
     */
    public const EVENT_TYPE_GATING_BLOCKED = 'batch.gating.blocked';

    public function __construct(
        private readonly VaccineBatchRepository $batches,
        private readonly PhaseTransitionRepository $transitions,
        private readonly CampusRepository $campuses,
        private readonly PhaseTransitionPolicy $policy,
        private readonly StructuredAuditLogger $auditLogger,
        private readonly GatingPolicy $gatingPolicy,
        private readonly EvaluateGatingRelease $evaluateGating,
        private readonly BatchSearchCache $searchCache,
        private readonly DomainEventRecorder $events,
    ) {}

    /**
     * Avanza el lote a la fase indicada y registra la transición.
     *
     * @throws ResourceNotFoundException Si el lote no existe.
     * @throws DomainException Si la transición no es secuencial.
     * @throws ConfigurationException Si el campus de destino no está dado de alta.
     */
    public function execute(
        string $batchId,
        BatchPhase $toPhase,
        ?string $actorId = null,
        ?DateTimeImmutable $now = null,
    ): PhaseAdvanceResult {
        $batch = $this->batches->findById($batchId);

        if ($batch === null) {
            throw ResourceNotFoundException::batch($batchId);
        }

        $fromPhase = $batch->phase();
        $this->policy->assertCanAdvance($fromPhase, $toPhase);

        $timestamp = $now ?? new DateTimeImmutable;

        if ($this->gatingPolicy->requiresApproval($toPhase)) {
            $this->assertGatingApproved($batch, $batchId, $toPhase, $actorId, $timestamp);
        }

        $campusName = $this->policy->campusNameFor($toPhase);
        $campus = $this->campuses->findByName($campusName);

        if ($campus === null) {
            throw ConfigurationException::missingCampus($campusName);
        }

        $batch->advanceTo($toPhase, $campus->id(), $this->policy, $timestamp);

        $transition = PhaseTransition::record($batchId, $fromPhase, $toPhase, $timestamp);

        $this->batches->save($batch);
        $this->transitions->append($transition);
        $this->searchCache->invalidate();

        $this->auditLogger->record(
            self::EVENT_TYPE,
            [
                'from_phase' => $fromPhase->value(),
                'to_phase' => $toPhase->value(),
                'current_campus_id' => $batch->currentCampusId(),
                'transition_id' => $transition->id(),
            ],
            $batchId,
            $actorId,
            $timestamp,
        );

        // El avance de fase se difunde en el canal de monitorización con la
        // fase de origen, la de destino y el campus vigente, que es lo que
        // el frontend necesita para resincronizar el lote (véase D11).
        $this->events->record(new BatchPhaseAdvanced(
            $batchId,
            $fromPhase->value(),
            $toPhase->value(),
            $batch->currentCampusId(),
            $batch->status()->value(),
            $transition->id(),
        ));

        return new PhaseAdvanceResult($batch, $transition);
    }

    /**
     * Exige la aprobación microbiológica antes de habilitar la fase logística.
     *
     * Sin aprobación, el lote queda bloqueado por gating y la operación
     * responde 422 sin avanzar de fase ni de campus (véase TC009).
     *
     * @throws DomainException Si el gating no se concede.
     */
    private function assertGatingApproved(
        VaccineBatch $batch,
        string $batchId,
        BatchPhase $toPhase,
        ?string $actorId,
        DateTimeImmutable $timestamp,
    ): void {
        $status = $this->evaluateGating->execute($batchId);

        if ($status->isApproved()) {
            return;
        }

        $reason = $status->reason() ?? 'falta la aprobación microbiológica de LIMS Labware.';

        $batch->blockByGating($timestamp);

        $this->batches->save($batch);
        $this->searchCache->invalidate();

        $this->auditLogger->record(
            self::EVENT_TYPE_GATING_BLOCKED,
            [
                'to_phase' => $toPhase->value(),
                'reason' => $reason,
                'status' => $batch->status()->value(),
            ],
            $batchId,
            $actorId,
            $timestamp,
        );

        throw DomainException::gatingNotApproved($reason);
    }
}
