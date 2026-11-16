<?php

declare(strict_types=1);

namespace App\Queries\Billing;

use App\Enums\Billing\FirmAccount;
use App\Models\Matter\Matter;

/* The money paid out on a matter for its billing tab, most recent first, and what it comes to. */
class MatterDisbursements
{
    /** @return array<string, mixed> */
    public function for(Matter $matter): array
    {
        $disbursements = $matter->disbursements()->with(['recordedBy', 'media'])
            ->orderByDesc('incurred_on')->orderByDesc('id')->get();

        return [
            'disbursements' => $disbursements,
            'disbursementsTotal' => (int) $disbursements->sum('amount_cents'),
            'firmAccounts' => FirmAccount::cases(),
        ];
    }
}
