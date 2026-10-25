<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Clients;

use App\Services\Clients\ResolveDateOfBirth;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class ResolveDateOfBirthTest extends TestCase
{
    public function test_it_reads_the_date_from_a_south_african_id(): void
    {
        $this->assertSame('1990-01-01', (new ResolveDateOfBirth)->fromSouthAfricanId('9001015800088')?->toDateString());
    }

    public function test_it_takes_the_century_that_does_not_put_the_birth_in_the_future(): void
    {
        CarbonImmutable::setTestNow('2026-10-24');

        $this->assertSame('2005-03-04', (new ResolveDateOfBirth)->fromSouthAfricanId('0503045800085')?->toDateString());
        $this->assertSame('1930-03-04', (new ResolveDateOfBirth)->fromSouthAfricanId('3003045800085')?->toDateString());
    }

    public function test_it_gives_nothing_for_a_number_that_holds_no_date(): void
    {
        $this->assertNull((new ResolveDateOfBirth)->fromSouthAfricanId('9013015800088'));
        $this->assertNull((new ResolveDateOfBirth)->fromSouthAfricanId('123'));
    }

    public function test_a_typed_date_has_to_agree_with_the_number(): void
    {
        $resolve = new ResolveDateOfBirth;

        $this->assertTrue($resolve->agrees('9001015800088', CarbonImmutable::parse('1990-01-01')));
        $this->assertFalse($resolve->agrees('9001015800088', CarbonImmutable::parse('1990-01-02')));
    }
}
