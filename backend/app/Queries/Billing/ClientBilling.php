<?php

declare(strict_types=1);

namespace App\Queries\Billing;

use App\Models\Billing\Bill;
use App\Models\Billing\Payment;
use App\Models\Client\Client;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;

/* A client's bills and payments across their matters, within the person's billing reach, and what is owed. */
class ClientBilling
{
    /** @return array<string, mixed> */
    public function for(Client $client, User $user): array
    {
        $onTheirMatters = fn (Builder $matters) => $matters->where('client_id', $client->getKey())->withBillingReachedBy($user);

        $bills = Bill::query()
            ->whereHas('matter', $onTheirMatters)
            ->with('matter')
            ->latest()
            ->latest('id')
            ->get();

        return [
            'bills' => $bills,
            'payments' => Payment::query()
                ->whereHas('bill.matter', $onTheirMatters)
                ->with('bill')
                ->latest('received_on')
                ->latest('id')
                ->get(),
            'owedCents' => (int) $bills->filter->isIssued()->sum(fn (Bill $bill) => $bill->balanceCents()),
        ];
    }
}
