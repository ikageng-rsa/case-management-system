<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Enums\Auth\Permission;
use App\Http\BillingHome;
use App\Http\Controllers\Controller;
use App\Models\Matter\Matter;
use App\Models\User\User;
use App\Queries\Billing\MatterBills;
use App\Queries\Billing\MatterDisbursements;
use App\Queries\Billing\MatterFeeAgreements;
use App\Queries\Billing\MatterWorkValue;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/*
 * A matter's billing by itself, for someone who works on the money without the
 * file: its fee agreement, disbursements and bills, and nothing of its ledger,
 * documents, parties or diary. Someone who may open the file is sent to its
 * Billing tab, so a matter's billing has one place for each person.
 */
class MatterBillingController extends Controller
{
    public function show(
        Request $request,
        Matter $matter,
        MatterFeeAgreements $feeAgreements,
        MatterDisbursements $disbursements,
        MatterWorkValue $workValue,
        MatterBills $bills,
    ): View|RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->can('manageFees', $matter) || $user->can('viewBills', $matter), 403);

        if ($user->can('view', $matter)) {
            return redirect()->to(BillingHome::url($matter));
        }

        $matter->load(['client', 'matterType']);
        $matter->recordAccess('read', ['ip' => $request->ip(), 'part' => 'billing']);

        return view('matter-billing', ['matter' => $matter]
            + $feeAgreements->for($matter)
            + $disbursements->for($matter)
            + $workValue->for($matter)
            + $bills->for($matter, $user->can(Permission::ViewUnmaskedClientData->value)));
    }
}
