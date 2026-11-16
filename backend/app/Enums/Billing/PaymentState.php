<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/*
 * How far an issued bill has been paid. Worked out from the payments on it, not
 * kept as the bill's status, so a bill stays issued however much is paid.
 */
enum PaymentState: string
{
    case Unpaid = 'unpaid';
    case PartPaid = 'part_paid';
    case Settled = 'settled';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::PartPaid => 'Part-paid',
            self::Settled => 'Settled',
        };
    }
}
