<?php

declare(strict_types=1);

namespace App\Rules\Client;

use App\Enums\Client\ContactKind;
use App\Services\Clients\NormaliseIdentifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidContactValue implements ValidationRule
{
    public function __construct(private readonly ContactKind $kind) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail("The :attribute is not a valid {$this->kind->value}.");

            return;
        }

        if (! $this->isWellFormed(NormaliseIdentifier::contact($this->kind, $value))) {
            $fail("The :attribute is not a valid {$this->kind->value}.");
        }
    }

    /** Mobile and email have a shape to check; addresses and contact people are free text. */
    private function isWellFormed(string $value): bool
    {
        return match ($this->kind) {
            ContactKind::Mobile => (bool) preg_match('/^\+\d{8,15}$/', $value),
            ContactKind::Email => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            default => $value !== '',
        };
    }
}
