<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/* What the drawing fee on a bill is a percentage of. It is shown under fees either way. */
enum DrawingFeeBase: string
{
    case Fees = 'fees';
    case FeesAndDisbursements = 'fees_and_disbursements';

    public function label(): string
    {
        return match ($this) {
            self::Fees => 'Fees only',
            self::FeesAndDisbursements => 'Fees and disbursements',
        };
    }
}
