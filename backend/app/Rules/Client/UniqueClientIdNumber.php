<?php

declare(strict_types=1);

namespace App\Rules\Client;

use App\Models\Client;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueClientIdNumber implements ValidationRule
{
    /** @param  string|null  $ignore  Client id to exclude, so an update can keep its own number. */
    public function __construct(private readonly ?string $ignore = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        // The column is encrypted, so uniqueness is checked against the blind
        // index the scope maintains
        $exists = Client::withTrashed()
            ->matchingIdNumber($value)
            ->when($this->ignore !== null, fn ($query) => $query->whereKeyNot($this->ignore))
            ->exists();

        if ($exists) {
            $fail('A client with this :attribute is already registered.');
        }
    }
}
