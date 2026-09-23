<?php

declare(strict_types=1);

namespace App\Services\Clients;

use RuntimeException;

/*
 * Personal information is encrypted at rest, which makes it unsearchable — the
 * same plaintext encrypts to a different ciphertext every time. Alongside each
 * encrypted column we store a keyed hash of the normalised value, so an exact
 * match can still be looked up without decrypting the whole table.
 */
class GenerateBlindIndex
{
    /** Hash a value for blind search, or null when there is nothing to hash. */
    public static function of(?string $value): ?string
    {
        $normalised = static::normalise($value);

        if ($normalised === null) {
            return null;
        }

        return hash_hmac('sha256', $normalised, static::key());
    }

    /*
     * Values are compared case-insensitively and free of surrounding or
     * repeated whitespace, so "  Jane@Example.com " and "jane@example.com"
     * resolve to the same hash.
     */
    protected static function normalise(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalised = preg_replace('/\s+/u', ' ', trim($value));

        if ($normalised === '') {
            return null;
        }

        return mb_strtolower($normalised);
    }

    protected static function key(): string
    {
        $key = config('blind-index.key');

        if (blank($key)) {
            throw new RuntimeException('No blind index key configured. Set BLIND_INDEX_KEY or APP_KEY.');
        }

        return $key;
    }
}
