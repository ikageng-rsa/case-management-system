<?php

declare(strict_types=1);

namespace App\Support\Clients;

use App\Enums\Client\ContactKind;
use App\Enums\Client\IdentityType;

class NormaliseIdentifier
{
    public static function idNumber(string $value): string
    {
        return preg_replace('/\D/', '', $value);
    }

    /*
     * A South African ID is bare digits; a passport or other document is letters and
     * digits, upper case, with the spaces and hyphens people type into it left out.
     */
    public static function identity(?IdentityType $type, string $value): string
    {
        if ($type === null || $type->isSouthAfrican()) {
            return self::idNumber($value);
        }

        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($value)) ?? '';
    }

    /*
     * What is hashed for the blind index. Any other kind of document has its type in
     * front, so numbers of different kinds never collide; a South African ID is hashed
     * bare, as it always was, so the numbers already on file still match.
     */
    public static function identityIndexKey(IdentityType $type, string $normalised): string
    {
        return $type->isSouthAfrican() ? $normalised : $type->value.':'.$normalised;
    }

    public static function registrationNumber(string $value): string
    {
        return mb_strtoupper(
            preg_replace('/\s+/', '', trim($value)) ?? ''
        );
    }

    public static function contact(ContactKind $kind, string $value): string
    {
        $value = trim($value);

        return match ($kind) {
            ContactKind::Mobile => self::mobile($value),
            ContactKind::Email => mb_strtolower($value),
            default => $value,
        };
    }

    public static function mobile(string $value): string
    {
        $digits = preg_replace('/[^\d+]/', '', $value);
        $raw = ltrim($digits, '+');   // work in raw digits, decide the '+' once at the end

        if (preg_match('/^0\d{9}$/', $raw) === 1) {
            return '+27'.substr($raw, 1);
        }

        if (preg_match('/^27\d{9}$/', $raw) === 1) {
            return '+'.$raw;
        }

        return str_starts_with($value, '+') ? '+'.$raw : $raw;
    }
}
