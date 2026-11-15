<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/* The firm's two bank accounts: the trust account holds clients' money, the business account the firm's own. */
enum FirmAccount: string
{
    case Trust = 'trust';
    case Business = 'business';

    public function label(): string
    {
        return match ($this) {
            self::Trust => 'Trust account',
            self::Business => 'Business account',
        };
    }
}
