<?php

namespace App\Enums\Narration;

enum ActivityMeasure: string
{
    case Minutes = 'minutes';
    case Page = 'page';
    case Kilometre = 'kilometre';
    case Item = 'item';

    /** Time is billed by the clock; everything else is counted. */
    public function isTimed(): bool
    {
        return $this === self::Minutes;
    }
}
