<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Client;

use App\Rules\Client\ValidForeignIdentity;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ValidForeignIdentityTest extends TestCase
{
    public function test_it_accepts_letters_and_digits_with_the_spacing_people_type(): void
    {
        $this->assertTrue($this->passes('A12345678'));
        $this->assertTrue($this->passes('ab 123-456'));
    }

    #[DataProvider('invalidNumbers')]
    public function test_it_rejects_what_cannot_be_a_document_number(string $value): void
    {
        $this->assertFalse($this->passes($value));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNumbers(): array
    {
        return [
            'too short' => ['A12'],
            'too long' => [str_repeat('A1', 13)],
            'punctuation' => ['A123/4567'],
        ];
    }

    private function passes(string $value): bool
    {
        $failed = false;

        (new ValidForeignIdentity)->validate('id_number', $value, function () use (&$failed): void {
            $failed = true;
        });

        return ! $failed;
    }
}
