<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\VaccineBatch;

use App\Domain\Lims\Entity\LimsResult;
use App\Domain\Lims\Service\GatingEvaluator;
use App\Domain\Shared\Exception\DomainException;
use App\Domain\VaccineBatch\Service\GatingPolicy;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Gating microbiológico del lote (véase D5 y TC009).
 */
class GatingPolicyTest extends TestCase
{
    private GatingEvaluator $evaluator;

    private GatingPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->evaluator = new GatingEvaluator;
        $this->policy = new GatingPolicy;
    }

    public function test_it_does_not_grant_the_gating_without_an_approved_microbiology_result(): void
    {
        $decision = $this->evaluator->evaluate([]);

        $this->assertFalse($decision->isApproved());
        $this->assertFalse($this->policy->canAdvance(
            BatchPhase::from(BatchPhase::SPHEREON),
            BatchPhase::from(BatchPhase::LOGISTICS),
            $decision,
        ));
    }

    public function test_it_does_not_grant_the_gating_when_the_microbiology_result_is_not_approved(): void
    {
        $decision = $this->evaluator->evaluate([
            $this->microbiologyResult('No conforme', 'Responsable de LIMS Labware'),
        ]);

        $this->assertFalse($decision->isApproved());
        $this->assertNotNull($decision->reason());
    }

    public function test_it_does_not_grant_the_gating_without_an_identified_approver(): void
    {
        $decision = $this->evaluator->evaluate([
            $this->microbiologyResult(LimsResult::RESULT_APPROVED, null),
        ]);

        $this->assertFalse($decision->isApproved());
    }

    public function test_it_does_not_grant_the_gating_when_the_result_is_not_registered_by_lims_labware(): void
    {
        $decision = $this->evaluator->evaluate([
            LimsResult::registered(
                '018f2f5e-1c2b-7a10-9b3d-2f4e5a6b7c8d',
                LimsResult::TEST_TYPE_MICROBIOLOGY,
                LimsResult::RESULT_APPROVED,
                'Responsable de LIMS Labware',
                new DateTimeImmutable('2026-02-10T08:30:00+00:00'),
            ),
        ]);

        $this->assertFalse($decision->isApproved());
    }

    public function test_it_grants_the_gating_with_an_approved_microbiology_result(): void
    {
        $decision = $this->evaluator->evaluate([
            $this->microbiologyResult(LimsResult::RESULT_APPROVED, 'Responsable de LIMS Labware'),
        ]);

        $this->assertTrue($decision->isApproved());
        $this->assertTrue($this->policy->canAdvance(
            BatchPhase::from(BatchPhase::SPHEREON),
            BatchPhase::from(BatchPhase::LOGISTICS),
            $decision,
        ));

        $this->policy->assertCanAdvance(
            BatchPhase::from(BatchPhase::SPHEREON),
            BatchPhase::from(BatchPhase::LOGISTICS),
            $decision,
        );
    }

    public function test_it_rejects_the_advance_to_the_logistics_phase_without_gating(): void
    {
        $decision = $this->evaluator->evaluate([]);

        try {
            $this->policy->assertCanAdvance(
                BatchPhase::from(BatchPhase::SPHEREON),
                BatchPhase::from(BatchPhase::LOGISTICS),
                $decision,
            );

            $this->fail('El avance a la fase logística debe rechazarse sin aprobación microbiológica.');
        } catch (DomainException $exception) {
            $this->assertSame('gating_not_approved', $exception->errorCode());
        }
    }

    public function test_it_does_not_demand_the_gating_for_the_previous_phases(): void
    {
        $decision = $this->evaluator->evaluate([]);

        $this->assertFalse($this->policy->requiresApproval(BatchPhase::from(BatchPhase::CULTURE)));
        $this->assertFalse($this->policy->requiresApproval(BatchPhase::from(BatchPhase::SPHEREON)));
        $this->assertTrue($this->policy->requiresApproval(BatchPhase::from(BatchPhase::LOGISTICS)));

        $this->policy->assertCanAdvance(
            BatchPhase::from(BatchPhase::CULTURE),
            BatchPhase::from(BatchPhase::SPHEREON),
            $decision,
        );
    }

    /**
     * Resultado microbiológico importado de LIMS Labware.
     */
    private function microbiologyResult(string $literal, ?string $approvedBy): LimsResult
    {
        return LimsResult::imported(
            '018f2f5e-1c2b-7a10-9b3d-2f4e5a6b7c8d',
            LimsResult::TEST_TYPE_MICROBIOLOGY,
            $literal,
            $approvedBy,
            new DateTimeImmutable('2026-02-10T08:30:00+00:00'),
        );
    }
}
