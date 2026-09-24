<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Clients;

use App\Enums\Client\ContactKind;
use App\Services\Clients\GenerateBlindIndex;
use App\Services\Clients\NormaliseIdentifier;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NormaliseIdentifierTest extends TestCase
{
    public function test_it_strips_everything_but_digits_from_an_id_number(): void
    {
        $this->assertSame('9001015800088', NormaliseIdentifier::idNumber('9001 015800 088'));
        $this->assertSame('9001015800088', NormaliseIdentifier::idNumber(' 900101-5800-088 '));
    }

    public function test_formatted_id_number_share_a_blind_index(): void
    {
        $this->assertSame(
            GenerateBlindIndex::of('9001015800088'),
            GenerateBlindIndex::of(NormaliseIdentifier::idNumber('9001 015800 088')),
        );
    }

    public function test_it_removes_whitespace_and_uppercases_a_registration_number(): void
    {
        $this->assertSame('2020/123456/07', NormaliseIdentifier::registrationNumber(' 2020 / 123456 / 07 '));
        $this->assertSame('IT1234/2020', NormaliseIdentifier::registrationNumber('it1234/2020'));
    }

    public function test_it_lowercases_and_trims_emails(): void
    {
        $this->assertSame('jane@example.com', NormaliseIdentifier::contact(ContactKind::Email, '  Jane@Example.COM'));
    }

    #[DataProvider('southAfricanPhoneFormats')]
    public function test_it_converts_local_phone_formats_to_e164(string $input): void
    {
        $this->assertSame('+27821234567', NormaliseIdentifier::contact(ContactKind::Mobile, $input));
    }

    /** @return array<string, array{string}> */
    public static function southAfricanPhoneFormats(): array
    {
        return [
            'local with spaces' => ['082 123 4567'],
            'local with brackets and dash' => ['(082) 123-4567'],
            'country code without plus' => ['27821234567'],
            'international with spaces' => ['+27 82 123 4567'],
        ];
    }
}