<?php

declare(strict_types=1);

namespace App\Casts\Client;

use App\Enums\Client\IdentityType;
use App\Support\Clients\NormaliseIdentifier;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Normalises an ID or passport number on the way in (digits for a South African ID,
 * letters and digits for anything else), then encrypts at rest.
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

        // The type is set before the number, so the number is normalised as what it is.
        $type = isset($attributes['id_type']) ? IdentityType::tryFrom($attributes['id_type']) : null;

        return Crypt::encryptString(NormaliseIdentifier::identity($type, (string) $value));
    }
}
