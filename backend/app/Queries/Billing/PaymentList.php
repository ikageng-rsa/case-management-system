<?php

declare(strict_types=1);

namespace App\Queries\Billing;

use App\Enums\Billing\FirmAccount;
use App\Models\Billing\Payment;
use App\Models\User\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/* Payments on the bills a person works on, newest first, with the total of those that count. */
class PaymentList
{
    public const PER_PAGE = 25;

    /** @return array{payments: LengthAwarePaginator, totalCents: int, count: int} */
    public function for(User $user, ?CarbonInterface $from = null, ?CarbonInterface $to = null, ?FirmAccount $account = null): array
    {
        $matching = fn (): Builder => Payment::query()
            ->whereHas('bill.matter', fn (Builder $matters) => $matters->withBillingReachedBy($user))
            ->when($from, fn (Builder $query) => $query->whereDate('received_on', '>=', $from))
            ->when($to, fn (Builder $query) => $query->whereDate('received_on', '<=', $to))
            ->when($account, fn (Builder $query) => $query->where('account', $account));

        return [
            'payments' => $matching()
                ->with(['bill.matter.client', 'recordedBy', 'reversedBy', 'media'])
                ->latest('received_on')
                ->latest('id')
                ->paginate(self::PER_PAGE)
                ->withQueryString(),
            'totalCents' => (int) $matching()->live()->sum('amount_cents'),
            'count' => $matching()->live()->count(),
        ];
    }
}
