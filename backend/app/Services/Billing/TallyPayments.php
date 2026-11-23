<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Billing\Bill;

class TallyPayments
{
    /** Set what a bill has been paid to the sum of the payments on it that still count. */
    public function tally(Bill $bill): Bill
    {
        $bill->update(['paid_cents' => (int) $bill->payments()->live()->sum('amount_cents')]);

        return $bill;
    }
}
