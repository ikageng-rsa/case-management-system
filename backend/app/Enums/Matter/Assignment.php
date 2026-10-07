<?php

declare(strict_types=1);

namespace App\Enums\Matter;

enum Assignment: string
{
    case Responsible = 'responsible';
    case Supervising = 'supervising';
    case Assisting = 'assisting';
    case Messenger = 'messenger';

    /** Responsible and Supervising are held by one person at a time; the rest may be shared. */
    public function isExclusive(): bool
    {
        return match ($this) {
            self::Responsible, self::Supervising => true,
            self::Assisting, self::Messenger => false,
        };
    }
}
