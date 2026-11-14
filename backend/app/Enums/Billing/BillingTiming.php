<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/* When statements are drawn: by hand, as a draft at each month end, or once a matter is finalised. */
enum BillingTiming: string
{
    case Manual = 'manual';
    case Monthly = 'monthly';
    case OnFinalisation = 'on_finalisation';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'When someone draws one',
            self::Monthly => 'A draft at the end of each month',
            self::OnFinalisation => 'When the matter is finalised',
        };
    }
}
