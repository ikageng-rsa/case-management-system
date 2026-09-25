<?php

declare(strict_types=1);

namespace App\Rules\Client;

use App\Services\Clients\NormaliseIdentifier;
use App\Services\Clients\ValidateSouthAfricanId;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidSouthAfricanId implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute is not valid.');

            return;
        }

        if (! ValidateSouthAfricanId::passes(NormaliseIdentifier::idNumber($value))) {
            $fail('The :attribute is not valid.');
        }
    }
}
