<?php

declare(strict_types=1);

namespace App\Enums\Court;

enum CourtTier: string
{
    case District = 'district';
    case Regional = 'regional';
    case High = 'high';
    case SupremeAppeal = 'supreme_appeal';
    case Constitutional = 'constitutional';

    public function courtName(): string
    {
        return match ($this) {
            self::District => "Magistrate's Court",
            self::Regional => 'Regional Court',
            self::High => 'High Court',
            self::SupremeAppeal => 'Supreme Court of Appeal',
            self::Constitutional => 'Constitutional Court',
        };
    }
}
