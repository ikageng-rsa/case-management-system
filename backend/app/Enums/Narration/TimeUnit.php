<?php

declare(strict_types=1);

namespace App\Enums\Narration;

/* The unit the time taken is typed in. It is kept in minutes. */
enum TimeUnit: string
{
    case Minutes = 'minutes';
    case Hours = 'hours';

    public function toMinutes(float $amount): int
    {
        return (int) round(match ($this) {
            self::Minutes => $amount,
            self::Hours => $amount * 60,
        });
    }
}
