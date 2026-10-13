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
        /** Contact id to leave out, so an update can keep its own value. */
        private readonly ?int $ignore = null,
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
            ->when($this->ignore !== null, fn ($query) => $query->whereKeyNot($this->ignore))
            ->exists();

        if ($exists) {
            $fail('This :attribute is already in the system.');
        }
    }
}
