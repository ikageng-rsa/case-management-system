<?php

declare(strict_types=1);

namespace App\Casts\Client;

use App\Enums\Client\ContactKind;
use App\Services\Clients\NormaliseIdentifier;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * A way of reaching someone that has a fixed kind of its own (a mobile column, an
 * email column): normalised for its kind on the way in, then encrypted at rest.
 * ContactValue does the same where the kind is stored next to the value.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class EncryptedContact implements CastsAttributes
{
    public function __construct(private readonly string $kind) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : Crypt::decryptString($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (blank($value)) {
            return null;
        }

        return Crypt::encryptString(NormaliseIdentifier::contact(ContactKind::from($this->kind), (string) $value));
    }
}
