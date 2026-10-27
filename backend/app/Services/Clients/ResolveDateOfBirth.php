<?php

declare(strict_types=1);

namespace App\Services\Clients;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/*
 * The first six digits of a South African ID are the date of birth, written
 * YYMMDD with no century. This reads it, and checks a date that was typed
 * against it so the two cannot say different things about the same person.
 */
class ResolveDateOfBirth
{
    /** The date the ID number says, taking the century that does not put the birth in the future. */
    public function fromSouthAfricanId(string $id): ?CarbonImmutable
    {
        if (strlen($id) < 6 || ! ctype_digit(substr($id, 0, 6))) {
            return null;
        }

        [$yy, $mm, $dd] = [(int) substr($id, 0, 2), (int) substr($id, 2, 2), (int) substr($id, 4, 2)];

        foreach ([2000 + $yy, 1900 + $yy] as $year) {
            if (checkdate($mm, $dd, $year) && ! CarbonImmutable::create($year, $mm, $dd)->isFuture()) {
                return CarbonImmutable::create($year, $mm, $dd)->startOfDay();
            }
        }

        return null;
    }

    /** Whether a date agrees with the one in the ID number: the century is not in the number, so only day, month and the last two digits of the year are compared. */
    public function agrees(string $id, CarbonInterface $date): bool
    {
        if (strlen($id) < 6) {
            return false;
        }

        return $date->format('ymd') === substr($id, 0, 6);
    }
}
