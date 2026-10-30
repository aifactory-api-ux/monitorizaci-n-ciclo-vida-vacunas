<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\VaccineBatch;

use App\Domain\Shared\Exception\DomainException;
use App\Domain\VaccineBatch\Entity\VaccineBatch;
use App\Domain\VaccineBatch\Service\PhaseTransitionPolicy;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;
use App\Domain\VaccineBatch\ValueObject\BatchStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class VaccineBatchTest extends TestCase
{
    private const CAMPUS_ONE = '11111111-1111-4111-8111-111111111111';

    private const CAMPUS_TWO = '22222222-2222-4222-8222-222222222222';

    private const CAMPUS_THREE = '33333333-3333-4333-8333-333333333333';

    private const REFERENCE = '44444444-4444-4444-8444-444444444444';

    public function test_a_new_batch_is_born_in_culture_phase_in_progress_at_campus_one(): void
    {
        $batch = VaccineBatch::create(self::REFERENCE, self::CAMPUS_ONE, new DateTimeImmutable);

        $this->assertSame(BatchPhase::CULTURE, $batch->phase()->value());
        $this->assertSame(BatchStatus::IN_PROGRESS, $batch->status()->value());
        $this->assertSame(self::CAMPUS_ONE, $batch->currentCampusId());
        $this->assertSame(self::REFERENCE, $batch->referenceId());
    }

    public function test_a_new_batch_has_a_uuid_primary_key(): void
    {
        $batch = VaccineBatch::create(self::REFERENCE, self::CAMPUS_ONE, new DateTimeImmutable);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $batch->id(),
        );
    }

    public function test_it_advances_the_phase_and_synchronises_the_campus(): void
    {
        $batch = VaccineBatch::create(self::REFERENCE, self::CAMPUS_ONE, new DateTimeImmutable);
        $policy = new PhaseTransitionPolicy;

        $batch->advanceTo(BatchPhase::from(BatchPhase::SPHEREON), self::CAMPUS_TWO, $policy, new DateTimeImmutable);

        $this->assertSame(BatchPhase::SPHEREON, $batch->phase()->value());
        $this->assertSame(self::CAMPUS_TWO, $batch->currentCampusId());
        $this->assertSame(BatchStatus::IN_PROGRESS, $batch->status()->value());
    }

    public function test_it_rejects_a_non_sequential_advance_without_mutating_the_batch(): void
    {
        $batch = VaccineBatch::create(self::REFERENCE, self::CAMPUS_ONE, new DateTimeImmutable);
        $policy = new PhaseTransitionPolicy;

        try {
            $batch->advanceTo(BatchPhase::from(BatchPhase::LOGISTICS), self::CAMPUS_THREE, $policy, new DateTimeImmutable);
            $this->fail('El avance no secuencial debe rechazarse.');
        } catch (DomainException) {
            $this->assertSame(BatchPhase::CULTURE, $batch->phase()->value());
            $this->assertSame(BatchStatus::IN_PROGRESS, $batch->status()->value());
            $this->assertSame(self::CAMPUS_ONE, $batch->currentCampusId());
        }
    }

    public function test_it_registers_the_physical_reception_in_the_logistics_phase(): void
    {
        $batch = $this->batchInLogisticsPhase();

        $batch->registerPhysicalReception(new DateTimeImmutable);

        $this->assertSame(BatchStatus::RECEIVED, $batch->status()->value());
        $this->assertSame(self::CAMPUS_THREE, $batch->currentCampusId());
    }

    public function test_it_rejects_the_physical_reception_outside_the_logistics_phase(): void
    {
        $batch = VaccineBatch::create(self::REFERENCE, self::CAMPUS_ONE, new DateTimeImmutable);

        $this->expectException(DomainException::class);

        $batch->registerPhysicalReception(new DateTimeImmutable);
    }

    public function test_it_closes_the_logistics_cycle_of_a_received_batch(): void
    {
        $batch = $this->batchInLogisticsPhase();
        $batch->registerPhysicalReception(new DateTimeImmutable);

        $batch->closeLogisticsCycle(new DateTimeImmutable);

        $this->assertSame(BatchStatus::CLOSED, $batch->status()->value());
        $this->assertSame(self::CAMPUS_THREE, $batch->currentCampusId());
    }

    public function test_it_rejects_the_closure_of_a_batch_that_was_not_received(): void
    {
        $batch = $this->batchInLogisticsPhase();

        $this->expectException(DomainException::class);

        $batch->closeLogisticsCycle(new DateTimeImmutable);
    }

    /**
     * Lote en la fase logística del Campus 3.
     */
    private function batchInLogisticsPhase(): VaccineBatch
    {
        $batch = VaccineBatch::create(self::REFERENCE, self::CAMPUS_ONE, new DateTimeImmutable);
        $policy = new PhaseTransitionPolicy;

        $batch->advanceTo(BatchPhase::from(BatchPhase::SPHEREON), self::CAMPUS_TWO, $policy, new DateTimeImmutable);
        $batch->advanceTo(BatchPhase::from(BatchPhase::LOGISTICS), self::CAMPUS_THREE, $policy, new DateTimeImmutable);

        return $batch;
    }
}
