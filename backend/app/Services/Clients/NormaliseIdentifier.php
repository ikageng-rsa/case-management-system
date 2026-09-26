<?php

declare(strict_types=1);

declare(strict_types=1);

namespace App\Services\Clients;

use App\Enums\Client\ContactKind; // Calling the enum class for the contact kind to do the normalisation of the identifier

class NormaliseIdentifier
{
    public static function idNumber(string $value): string
    {
        return preg_replace('/\D/', '', $value); // Remove all non-digit characters from the input string, effectively normalizing it to just the digits.
    }

    public static function registrationNumber(string $value): string
    {
        return mb_strtoupper(
            preg_replace('/\s+/', '', trim($value)) ?? ''
        ); // Remove all whitespace characters from the input string, effectively normalizing it.
    }

    public static function contact(ContactKind $kind, string $value): string
    {
        $value = trim($value); // Remove whitespace from the beginning and end of the input string.

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
        if (preg_match('/^27\d{9}$/', $raw) === 1) {
            return '+'.$raw;
        }

        return str_starts_with($value, '+') ? '+'.$raw : $raw;
    }
}