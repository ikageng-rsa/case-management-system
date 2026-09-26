<?php

declare(strict_types=1);

namespace App\Casts\Client;

use App\Services\Clients\NormaliseIdentifier;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Normalises an ID number to bare digits on the way in, then encrypts at rest.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class IdNumber implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : Crypt::decryptString($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return Crypt::encryptString(NormaliseIdentifier::idNumber((string) $value));
    }
}
