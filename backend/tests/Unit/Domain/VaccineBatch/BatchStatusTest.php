<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\VaccineBatch;

use App\Domain\Shared\Exception\DomainException;
use App\Domain\VaccineBatch\ValueObject\BatchStatus;
use PHPUnit\Framework\TestCase;

class BatchStatusTest extends TestCase
{
    public function test_it_exposes_the_statuses_of_the_batch(): void
    {
        $this->assertSame([
            'En curso',
            'Recibido',
            'Cerrado',
            'Bloqueado por Gating LIMS',
        ], BatchStatus::values());
    }

    public function test_it_builds_a_status_from_its_literal(): void
    {
        $this->assertSame(BatchStatus::IN_PROGRESS, BatchStatus::from('En curso')->value());
        $this->assertSame(BatchStatus::RECEIVED, BatchStatus::from('Recibido')->value());
        $this->assertSame(BatchStatus::CLOSED, BatchStatus::from('Cerrado')->value());
        $this->assertSame(BatchStatus::BLOCKED_BY_GATING, BatchStatus::from('Bloqueado por Gating LIMS')->value());
    }

    public function test_it_rejects_an_unknown_status_literal(): void
    {
        $this->expectException(DomainException::class);

        BatchStatus::from('En espera');
    }

    public function test_it_returns_null_for_an_unknown_status_literal(): void
    {
        $this->assertNull(BatchStatus::tryFrom('En espera'));
        $this->assertNull(BatchStatus::tryFrom(''));
    }

    public function test_it_compares_statuses_by_their_literal(): void
    {
        $this->assertTrue(BatchStatus::from(BatchStatus::RECEIVED)->equals(BatchStatus::from(BatchStatus::RECEIVED)));
        $this->assertFalse(BatchStatus::from(BatchStatus::RECEIVED)->equals(BatchStatus::from(BatchStatus::CLOSED)));
    }
}
