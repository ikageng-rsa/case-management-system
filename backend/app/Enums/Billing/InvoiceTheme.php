<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/* How a bill is laid out when it is printed. */
enum InvoiceTheme: string
{
    case Classic = 'classic';
    case Modern = 'modern';
    case Minimal = 'minimal';

    public function label(): string
    {
        return match ($this) {
            self::Classic => 'Classic',
            self::Modern => 'Modern',
            self::Minimal => 'Minimal',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Classic => 'Serif type, a black-ruled reference box and the heading between black rules. Formal, like a letterhead.',
            self::Modern => 'The reference box, heading and column titles filled with the accent colour.',
            self::Minimal => 'Plain type, light grey rules and no boxes, with plenty of white space.',
        };
    }
}
