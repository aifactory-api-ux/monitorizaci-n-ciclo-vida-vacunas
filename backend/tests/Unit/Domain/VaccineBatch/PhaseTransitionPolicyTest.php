<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\VaccineBatch;

use App\Domain\Shared\Exception\DomainException;
use App\Domain\VaccineBatch\Entity\Campus;
use App\Domain\VaccineBatch\Service\PhaseTransitionPolicy;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;
use PHPUnit\Framework\TestCase;

class PhaseTransitionPolicyTest extends TestCase
{
    private PhaseTransitionPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new PhaseTransitionPolicy;
    }

    public function test_it_admits_the_sequential_transitions_of_the_life_cycle(): void
    {
        $this->assertTrue($this->policy->canAdvance(
            BatchPhase::from(BatchPhase::CULTURE),
            BatchPhase::from(BatchPhase::SPHEREON),
        ));

        $this->assertTrue($this->policy->canAdvance(
            BatchPhase::from(BatchPhase::SPHEREON),
            BatchPhase::from(BatchPhase::LOGISTICS),
        ));
    }

    public function test_it_rejects_a_skipped_phase(): void
    {
        $this->assertFalse($this->policy->canAdvance(
            BatchPhase::from(BatchPhase::CULTURE),
            BatchPhase::from(BatchPhase::LOGISTICS),
        ));
    }

    public function test_it_rejects_a_rollback(): void
    {
        $this->assertFalse($this->policy->canAdvance(
            BatchPhase::from(BatchPhase::SPHEREON),
            BatchPhase::from(BatchPhase::CULTURE),
        ));
    }

    public function test_it_rejects_any_repetition_of_phase(): void
    {
        foreach (BatchPhase::values() as $phase) {
            $this->assertFalse(
                $this->policy->canAdvance(BatchPhase::from($phase), BatchPhase::from($phase)),
                sprintf('La repetición de la fase "%s" debe rechazarse.', $phase),
            );
        }
    }

    public function test_it_rejects_the_advance_from_the_last_phase(): void
    {
        $this->assertFalse($this->policy->canAdvance(
            BatchPhase::from(BatchPhase::LOGISTICS),
            BatchPhase::from(BatchPhase::SPHEREON),
        ));
    }

    public function test_it_demands_a_sequential_transition(): void
    {
        $this->expectException(DomainException::class);

        $this->policy->assertCanAdvance(
            BatchPhase::from(BatchPhase::CULTURE),
            BatchPhase::from(BatchPhase::LOGISTICS),
        );
    }

    public function test_it_returns_the_next_phase_of_the_sequence(): void
    {
        $this->assertSame(
            BatchPhase::SPHEREON,
            $this->policy->nextPhase(BatchPhase::from(BatchPhase::CULTURE))?->value(),
        );

        $this->assertSame(
            BatchPhase::LOGISTICS,
            $this->policy->nextPhase(BatchPhase::from(BatchPhase::SPHEREON))?->value(),
        );

        $this->assertNull($this->policy->nextPhase(BatchPhase::from(BatchPhase::LOGISTICS)));
    }

    public function test_it_maps_every_phase_to_its_campus(): void
    {
        $this->assertSame(Campus::CULTURE, $this->policy->campusNameFor(BatchPhase::from(BatchPhase::CULTURE)));
        $this->assertSame(Campus::SPHEREON, $this->policy->campusNameFor(BatchPhase::from(BatchPhase::SPHEREON)));
        $this->assertSame(Campus::LOGISTICS, $this->policy->campusNameFor(BatchPhase::from(BatchPhase::LOGISTICS)));
    }
}
