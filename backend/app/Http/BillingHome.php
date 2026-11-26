<?php

declare(strict_types=1);

namespace App\Http;

use App\Models\Matter\Matter;
use App\Models\User\User;
use Illuminate\Support\Facades\Auth;

/* The matter's Billing tab for someone who may open the file, otherwise its billing page. */
class BillingHome
{
    public static function url(Matter $matter, ?User $user = null): string
    {
        $user ??= Auth::user();

        if ($user?->can('view', $matter)) {
            return route('matter.show', [$matter, 'tab' => 'billing']);
        }

        return route('billing.matter', $matter);
    }
}
