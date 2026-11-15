<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Billing\Bill;

class DeleteDraftBill
{
    /**
     * Throw away a draft, which frees the work on it for the next bill. An
     * issued bill is part of the books and is voided instead.
     */
    public function delete(Bill $bill): void
    {
        if (! $bill->isDraft()) {
            throw new BillRefused(['Only a draft can be deleted. An issued bill is voided instead.']);
        }

        $bill->delete();
    }
}
