<?php

declare(strict_types=1);

namespace App\Services\Clients;

class ValidateSouthAfricanId
{
    public static function passes(string $id): bool
    {
        if (strlen($id) !== 13 || ! ctype_digit($id)) {
            return false;
        }

        // Digits 1-6 are YYMMDD. The century isn't encoded, so accept either,
        $yy = (int) substr($id, 0, 2);
        $mm = (int) substr($id, 2, 2);
        $dd = (int) substr($id, 4, 2);

        if (! checkdate($mm, $dd, 2000 + $yy) && ! checkdate($mm, $dd, 1900 + $yy)) {
            return false;
        }

        // Digit 11: Citizenship (0 = citizen, 1 = permanent resident).
        if (! in_array($id[10], ['0', '1'], true)) {
            return false;
        }

        // Digit 13 : Luhn checksum
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $digit = (int) $id[12 - $i];
            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }

        return $sum % 10 === 0;
    }
}
