<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Billing\BillLine;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;

class RemoveBillLine
{
    public function __construct(
        protected TotalBill $totalBill,
    ) {}

    /**
     * Take a line off a draft. The work or cost it charged is free again for a
     * later bill, and the draft's totals are worked out afresh.
     */
    public function remove(BillLine $line, User $by): void
    {
        $bill = $line->bill;

        if (! $bill->isDraft()) {
            throw new BillRefused(['Only a draft can be changed.']);
        }

        if ($bill->lines()->count() === 1) {
            throw new BillRefused(['This is the only line on the bill. Delete the draft instead.']);
        }

        DB::transaction(function () use ($line, $bill, $by): void {
            $line->delete();

            $this->totalBill->total($bill);

            $bill->recordEvent('line_removed', "{$by->name} removed a line from {$bill->auditLabel()}", [
                'description' => $line->description,
                'amount_cents' => $line->amount(),
            ]);
        });
    }
}
