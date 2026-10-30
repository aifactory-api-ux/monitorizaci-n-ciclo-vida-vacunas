<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\VaccineBatch;

use App\Domain\Shared\Exception\DomainException;
use App\Domain\VaccineBatch\ValueObject\BatchPhase;
use PHPUnit\Framework\TestCase;

class BatchPhaseTest extends TestCase
{
    public function test_it_exposes_the_phases_of_the_life_cycle(): void
    {
        $this->assertSame([
            'Fase de cultivo',
            'Formulación Sphereon (Campus 2)',
            'Fase logística (Campus 3)',
        ], BatchPhase::values());
    }

    public function test_it_builds_a_phase_from_its_literal(): void
    {
        $this->assertSame(BatchPhase::CULTURE, BatchPhase::from('Fase de cultivo')->value());
        $this->assertSame(BatchPhase::SPHEREON, BatchPhase::from('Formulación Sphereon (Campus 2)')->value());
        $this->assertSame(BatchPhase::LOGISTICS, BatchPhase::from('Fase logística (Campus 3)')->value());
    }

    public function test_it_rejects_an_unknown_phase_literal(): void
    {
        $this->expectException(DomainException::class);

        BatchPhase::from('Fase inexistente');
    }

    public function test_it_returns_null_for_an_unknown_phase_literal(): void
    {
        $this->assertNull(BatchPhase::tryFrom('Fase inexistente'));
        $this->assertNull(BatchPhase::tryFrom(''));
    }

    public function test_it_compares_phases_by_their_literal(): void
    {
        $this->assertTrue(BatchPhase::from(BatchPhase::CULTURE)->equals(BatchPhase::from(BatchPhase::CULTURE)));
        $this->assertFalse(BatchPhase::from(BatchPhase::CULTURE)->equals(BatchPhase::from(BatchPhase::LOGISTICS)));
    }
}
