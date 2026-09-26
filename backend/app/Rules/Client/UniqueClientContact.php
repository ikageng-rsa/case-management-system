<?php

declare(strict_types=1);

namespace App\Rules\Client;

use App\Enums\Client\ContactKind;
use App\Models\Client;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueClientContact implements ValidationRule
{
    public function __construct(
        private readonly Client $client,
        private readonly ContactKind $kind,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        // The scope normalises and blind-indexes the value; the check is per
        // client, so different clients may share a contact.
        $exists = $this->client->contacts()
            ->matchingValue($value, $this->kind)
            ->exists();

        if ($exists) {
            $fail('This :attribute is already in the system.');
        }
    }
}
