<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Client;

use App\Enums\Client\ContactKind;
use App\Rules\Client\ValidContactValue;
use Tests\TestCase;

class ValidContactValueTest extends TestCase
{
    public function test_it_accepts_a_valid_email(): void
    {
        $this->assertTrue($this->passes(ContactKind::Email, 'jane@example.com'));
    }

    public function test_it_rejects_a_malformed_email(): void
    {
        $this->assertFalse($this->passes(ContactKind::Email, 'not-an-email'));
    }

    public function test_it_accepts_a_south_african_mobile_number_in_any_format(): void
    {
        $this->assertTrue($this->passes(ContactKind::Mobile, '082 123 4567'));
        $this->assertTrue($this->passes(ContactKind::Mobile, '+27 82 123 4567'));
    }

    public function test_it_rejects_a_too_short_mobile_number(): void
    {
        $this->assertFalse($this->passes(ContactKind::Mobile, '12345'));
    }

    public function test_it_accepts_any_non_empty_postal_address(): void
    {
        $this->assertTrue($this->passes(ContactKind::Postal, 'PO Box 1, Cape Town'));
    }

    public function test_it_rejects_a_blank_address(): void
    {
        $this->assertFalse($this->passes(ContactKind::Physical, '   '));
    }

    private function passes(ContactKind $kind, string $value): bool
    {
        $failed = false;

        (new ValidContactValue($kind))->validate(
            'value',
            $value,
            function () use (&$failed): void {
                $failed = true;
            },
        );

        return ! $failed;
    }
}
