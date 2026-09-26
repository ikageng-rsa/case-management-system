<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Client;

use App\Rules\Client\ValidRegistrationNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ValidRegistrationNumberTest extends TestCase
{
    #[DataProvider('validNumbers')]
    public function test_it_accepts_well_formed_registration_numbers(string $number): void
    {
        $this->assertTrue($this->passes($number));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validNumbers(): array
    {
        return [
            'standard company' => ['2020/123456/07'],
            'spacing is ignored' => ['2020 / 123456 / 07'],
            'lower-case letters are upper-cased' => ['it1234'],
        ];
    }

    #[DataProvider('invalidNumbers')]
    public function test_it_rejects_malformed_registration_numbers(string $number): void
    {
        $this->assertFalse($this->passes($number));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNumbers(): array
    {
        return [
            'too short' => ['A/1'],
            'illegal characters' => ['!!'],
            'too long' => [str_repeat('9', 26)],
        ];
    }

    private function passes(string $value): bool
    {
        $failed = false;

        (new ValidRegistrationNumber)->validate(
            'registration_number',
            $value,
            function () use (&$failed): void {
                $failed = true;
            },
        );

        return ! $failed;
    }
}
