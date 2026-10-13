<?php

declare(strict_types=1);

namespace App\Services\Clients;

use Illuminate\Support\Str;

/*
 * How a client's identifiers read to someone who is not cleared to see them in
 * full. Enough is left to recognise a record, never enough to reuse it.
 */
class MaskIdentifier
{
    private const DOT = '·';

    /** +27721234418 reads as "072 ··· 4418". */
    public static function mobile(string $value): string
    {
        $local = preg_replace('/^\+27/', '0', $value) ?? $value;

        if (mb_strlen($local) < 8) {
            return Str::mask($local, self::DOT, 0);
        }

        $masked = Str::mask($local, self::DOT, 3, -4);

        return mb_substr($masked, 0, 3).' '.mb_substr($masked, 3, -4).' '.mb_substr($masked, -4);
    }

    /** t.mokoena@example.com reads as "t.mokoena@·······.com". */
    public static function email(string $value): string
    {
        [$local, $domain] = array_pad(explode('@', $value, 2), 2, '');

        if ($domain === '') {
            return Str::mask($value, self::DOT, 0);
        }

        // The top-level domain stays; it says little and helps recognition.
        return $local.'@'.Str::mask($domain, self::DOT, 0, mb_strlen(Str::beforeLast($domain, '.')));
    }

    /** 9001015800088 reads as "9001015······". */
    public static function idNumber(string $value): string
    {
        return Str::mask($value, self::DOT, 7);
    }

    /** 2011/123456/07 reads as "2011/······/07". */
    public static function registrationNumber(string $value): string
    {
        $parts = explode('/', $value);

        if (count($parts) !== 3) {
            return Str::mask($value, self::DOT, 0);
        }

        return $parts[0].'/'.Str::mask($parts[1], self::DOT, 0).'/'.$parts[2];
    }

    /** 1142 Boitekong Ext 4, Rustenburg reads as "1142 Boi···". Enough to recognise, not to find. */
    public static function address(string $value): string
    {
        return Str::mask($value, self::DOT, min(8, max(mb_strlen($value) - 3, 0)));
    }
}
