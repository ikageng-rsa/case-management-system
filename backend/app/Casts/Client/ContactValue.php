<?php

declare(strict_types=1);

namespace App\Casts\Client;

use App\Enums\Client\ContactKind;
use App\Services\Clients\NormaliseIdentifier;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use LogicException;

/**
 * Normalises a contact value against its kind on the way in, then encrypts at
 * rest. The kind must be set before the value.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class ContactValue implements CastsAttributes
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

        return Crypt::encryptString(
            NormaliseIdentifier::contact($this->kind($model, $attributes), (string) $value),
        );
    }

    private function kind(Model $model, array $attributes): ContactKind
    {
        $kind = $attributes['kind'] ?? $model->kind;

        if ($kind instanceof ContactKind) {
            return $kind;
        }

        if (is_string($kind)) {
            return ContactKind::from($kind);
        }

        throw new LogicException('A contact value cannot be normalised before its kind is set.');
    }
}
