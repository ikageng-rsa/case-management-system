<?php

declare(strict_types=1);

namespace App\Rules\Client;

use App\Services\Clients\NormaliseIdentifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidRegistrationNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute is not valid.');

            return;
        }

        /*
         * Deliberately permissive: trusts, NPCs and older companies don't all
         * follow the 2020/123456/07 pattern
         */
        if (preg_match('#^[A-Z0-9/\-]{5,25}$#', NormaliseIdentifier::registrationNumber($value)) !== 1) {
            $fail('The :attribute is not valid.');
        }
    }
}
