<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\BillStatus;
use App\Models\Billing\Bill;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;

class VoidBill
{
    /**
     * Cancel a bill issued in error. It keeps its number and stays on record
     * with the reason, nothing is owed on it any more, and the work and costs
     * on it are free to be billed again. A draft is deleted instead.
     */
    public function void(Bill $bill, User $by, string $reason): Bill
    {
        if (! $bill->isIssued()) {
            throw new BillRefused(['Only an issued bill can be voided. A draft is deleted instead.']);
        }

        return DB::transaction(function () use ($bill, $by, $reason): Bill {
            $bill->forceFill(['voided_by' => $by->getKey()]);
            $bill->update([
                'status' => BillStatus::Void,
                'voided_at' => now(),
                'void_reason' => trim($reason),
            ]);

            $bill->lines()->update(['claim' => null]);

            return $bill;
        });
    }
}
