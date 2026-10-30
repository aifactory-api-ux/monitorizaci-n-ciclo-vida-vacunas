<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared;

use App\Domain\Shared\Exception\DomainException;
use App\Domain\Shared\ValueObject\Uuid;
use PHPUnit\Framework\TestCase;

class UuidTest extends TestCase
{
    public function test_it_generates_a_valid_random_uuid_v4(): void
    {
        $uuid = Uuid::v4();

        $this->assertTrue(Uuid::isValid($uuid));
        $this->assertSame(4, Uuid::version($uuid));
    }

    public function test_it_generates_a_valid_time_ordered_uuid_v7(): void
    {
        $uuid = Uuid::v7();

        $this->assertTrue(Uuid::isValid($uuid));
        $this->assertSame(7, Uuid::version($uuid));
    }

    public function test_the_uuid_v7_embeds_the_current_timestamp(): void
    {
        $before = (int) floor(microtime(true) * 1000);
        $uuid = Uuid::v7();
        $after = (int) floor(microtime(true) * 1000);

        $milliseconds = (int) hexdec(substr(str_replace('-', '', $uuid), 0, 12));

        $this->assertGreaterThanOrEqual($before, $milliseconds);
        $this->assertLessThanOrEqual($after, $milliseconds);
    }

    public function test_it_generates_different_identifiers_on_each_call(): void
    {
        $this->assertNotSame(Uuid::v4(), Uuid::v4());
        $this->assertNotSame(Uuid::v7(), Uuid::v7());
    }

    public function test_it_rejects_identifiers_that_are_not_uuids(): void
    {
        $this->assertFalse(Uuid::isValid('no-es-un-uuid'));
        $this->assertFalse(Uuid::isValid(''));
        $this->assertFalse(Uuid::isValid('123e45678e89b12d3a456426614174000'));
    }

    public function test_it_rejects_the_version_of_a_malformed_identifier(): void
    {
        $this->expectException(DomainException::class);

        Uuid::version('no-es-un-uuid');
    }
}
