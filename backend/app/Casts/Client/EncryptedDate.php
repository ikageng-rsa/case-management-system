<?php

declare(strict_types=1);

namespace App\Casts\Client;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * A calendar date kept encrypted at rest: a date of birth is personal information
 * in its own right. Read back as a date with no time of day.
 *
 * @implements CastsAttributes<CarbonImmutable|null, CarbonInterface|string|null>
 */
class EncryptedDate implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::createFromFormat('Y-m-d', Crypt::decryptString($value))->startOfDay();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = $value instanceof CarbonInterface ? $value : CarbonImmutable::parse((string) $value);

        return Crypt::encryptString($date->format('Y-m-d'));
    }
}
