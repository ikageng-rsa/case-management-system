<?php

declare(strict_types=1);

namespace App\Http;

use App\Models\Matter\Matter;
use App\Models\User\User;
use Illuminate\Support\Facades\Auth;

/*
 * Where a matter's billing is shown to a person. Someone who may open the file
 * sees it as the Billing tab of the matter. Someone who works on billing
 * without the file is sent to the matter's billing page instead, and is never
 * handed a link into the file.
 */
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
