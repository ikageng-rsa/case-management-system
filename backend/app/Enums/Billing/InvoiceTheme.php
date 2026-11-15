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
            self::Classic => 'Serif type and black rules. Formal, like a letterhead.',
            self::Modern => 'A band of the accent colour across the top, and accented headings.',
            self::Minimal => 'Plain type, light grey rules and plenty of white space.',
        };
    }
}
