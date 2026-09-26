<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Client;

use App\Rules\Client\ValidSouthAfricanId;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ValidSouthAfricanIdTest extends TestCase
{
    public function test_it_accepts_a_well_formed_id_number(): void
    {
        $this->assertTrue($this->passes('9001015800088'));
        $this->assertTrue($this->passes('8505055009088'));
    }

    public function test_it_accepts_a_leap_day_birth_date(): void
    {
        $this->assertTrue($this->passes('9602295800084'));
    }

    public function test_it_ignores_spacing_around_the_digits(): void
    {
        $this->assertTrue($this->passes('9001 015800 088'));
    }

    #[DataProvider('invalidIdNumbers')]
    public function test_it_rejects_invalid_id_numbers(string $id): void
    {
        $this->assertFalse($this->passes($id));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidIdNumbers(): array
    {
        return [
            'wrong checksum' => ['900101580003'],
            'too short' => ['90010158008'],
            'too long' => ['90010158000885678'],
            'contains letters' => ['90010158000A8'],
            'impossible data (30 Feb)' => ['9002305800085'],
            'unknown citizenship digit' => ['9001015800286'],
            'empty' => [''],
        ];
    }

    /** Run the rule against a value and report whether it passed. */
    private function passes(string $id): bool
    {
        $failed = false;

        (new ValidSouthAfricanId)->validate(
            'id_number',
            $id,
            function () use (&$failed): void {
                $failed = true;
            },
        );

        return ! $failed;
    }
}
