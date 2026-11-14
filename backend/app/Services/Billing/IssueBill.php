<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\BillStatus;
use App\Models\Billing\Bill;
use App\Models\User\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class IssueBill
{
    public const PREFIX = 'INV';

    /**
     * Issue a checked draft. It is locked as it stands and numbered in the
     * firm's running sequence for the year, such as INV/2026/0001, and from
     * then on what it says is owed. Two bills issued at once cannot share a
     * number: the number is unique, so the second takes the next one.
     */
    public function issue(Bill $bill, User $by): Bill
    {
        if (! $bill->isDraft()) {
            throw new BillRefused(['Only a draft can be issued.']);
        }

        if ($bill->lines()->doesntExist()) {
            throw new BillRefused(['A bill with no lines cannot be issued. Add work or delete the draft.']);
        }

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($bill, $by): Bill {
                    $bill->forceFill(['issued_by' => $by->getKey()]);
                    $bill->update([
                        'status' => BillStatus::Issued,
                        'number' => $this->nextNumber((int) now()->format('Y')),
                        'issued_at' => now(),
                    ]);

                    return $bill;
                });
            } catch (QueryException $e) {
                if ($attempt === 3) {
                    throw $e;
                }

                $bill->refresh();
            }
        }
    }

    /** The next number in the year's sequence, e.g. INV/2026/0001. */
    protected function nextNumber(int $year): string
    {
        $prefix = self::PREFIX."/{$year}/";

        $last = Bill::query()->where('number', 'like', "{$prefix}%")->pluck('number')
            ->map(fn (string $number) => (int) substr($number, strlen($prefix)))
            ->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }
}
