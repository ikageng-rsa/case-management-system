<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Clients;

use App\Services\Clients\ValidateSouthAfricanId;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ValidateSouthAfricanIdTest extends TestCase
{
    public function test_it_accepts_a_well_formed_id_number(): void
    {
        $this->assertTrue(ValidateSouthAfricanId::passes('9001015800088'));
        $this->assertTrue(ValidateSouthAfricanId::passes('8505055009088'));

    }

    public function test_it_accepts_a_leap_day_birth_date(): void
    {
        $this->assertTrue(ValidateSouthAfricanId::passes('9602295800084'));
    }

    #[DataProvider('invalidIdNumbers')]
    public function test_it_rejects_invalid_id_numbers(string $id): void
    {
        $this->assertFalse(ValidateSouthAfricanId::passes($id));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidIdNumbers(): array
    {
        return [
            'wrong checksum' => ['900101580003'],
            'too short' => ['90010158008'],
            'too long' => ['9001015800088'],
            'contains letters' => ['90010158000A8'],
            'contains spaces' => ['9001 015800 088'],
            'impossible data (30 Feb)' => ['9002305800085'],
            'unknown citizenship digit' => ['9001015800286'],
            'empty' => [''],
        ];
    }
}
