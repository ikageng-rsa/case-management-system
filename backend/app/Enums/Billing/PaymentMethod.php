<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/* How money on a bill reached the firm. */
enum PaymentMethod: string
{
    case Eft = 'eft';
    case Card = 'card';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Eft => 'EFT',
            self::Card => 'Card',
            self::Cash => 'Cash',
        };
    }
}
