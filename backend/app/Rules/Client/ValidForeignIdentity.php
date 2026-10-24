<?php

declare(strict_types=1);

namespace App\Rules\Client;

use App\Enums\Client\IdentityType;
use App\Services\Clients\NormaliseIdentifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/*
 * A passport or other identity document number. There is no common format across
 * countries, so all that can be asked is that what is left once spaces and hyphens
 * are put aside is a plausible run of letters and digits.
 */
class ValidForeignIdentity implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $clean = is_string($value) ? NormaliseIdentifier::identity(IdentityType::Passport, $value) : '';

        // Anything other than letters, digits, spaces and hyphens is not a document number.
        $plain = is_string($value) && preg_match('/^[A-Za-z0-9 \-]+$/', $value) === 1;

        if (! $plain || strlen($clean) < 4 || strlen($clean) > 25) {
            $fail('The :attribute must be 4 to 25 letters and digits.');
        }
    }
}
