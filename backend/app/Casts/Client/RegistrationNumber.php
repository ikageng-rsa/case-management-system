<?php

declare(strict_types=1);

namespace App\Casts\Client;

use App\Services\Clients\NormaliseIdentifier;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Normalises a registration number to an upper-case, whitespace-free form.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class RegistrationNumber implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : NormaliseIdentifier::registrationNumber((string) $value);
    }
}
