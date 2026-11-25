<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Enums\Auth\Permission;
use App\Enums\Billing\BillKind;
use App\Enums\Billing\FirmAccount;
use App\Enums\Billing\PaymentMethod;
use App\Http\BillingHome;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreBillRequest;
use App\Models\Billing\Bill;
use App\Models\Matter\Matter;
use App\Models\User\User;
use App\Queries\Billing\BillList;
use App\Queries\Billing\UnbilledWork;
use App\Services\Billing\BillRefused;
use App\Services\Billing\DeleteDraftBill;
use App\Services\Billing\DraftBill;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BillsController extends Controller
{
    public function index(Request $request, BillList $billList): View
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->can(Permission::ViewBills->value), 403);

        $show = array_key_exists((string) $request->query('show'), BillList::SHOWING) ? (string) $request->query('show') : 'all';
        $search = is_string($request->query('q')) ? trim($request->query('q')) : '';

        return view('bills', [
            'show' => $show,
            'showing' => BillList::SHOWING,
            'search' => $search,
        ] + $billList->for($user, $show, $search));
    }

    public function store(StoreBillRequest $request, Matter $matter, DraftBill $draftBill): RedirectResponse
    {
        Gate::authorize('draftBills', $matter);

        try {
            $bill = $draftBill->draft(
                matter: $matter,
                kind: $request->kind(),
                addressee: $request->validated('addressee'),
                draftedBy: $request->user(),
                scale: $request->scale(),
                from: $request->from(),
                to: $request->to(),
                timeRates: $request->timeRates(),
                address: $request->validated('address'),
            );
        } catch (BillRefused $e) {
            return redirect()->to(BillingHome::url($matter))
                ->withInput()->withErrors(['bill' => $e->problems], 'bill');
        }

        return redirect()->route('bill.show', $bill)->with('status', 'Draft drawn. Check it before it is issued.');
    }

    public function show(Bill $bill, UnbilledWork $unbilled): View
    {
        Gate::authorize('viewBills', $bill->matter);

        $bill->load(['matter.client', 'lines.user', 'lines.overriddenBy', 'draftedBy', 'issuedBy', 'voidedBy', 'payments.recordedBy', 'payments.reversedBy', 'payments.media']);

        return view('bill', [
            'bill' => $bill,
            // What a draft can still take: the matter's work and costs not yet billed on a bill of its kind.
            'unbilled' => $bill->isDraft() ? $unbilled->for($bill->matter, $bill->kind) : null,
            'paymentMethods' => PaymentMethod::cases(),
            'firmAccounts' => FirmAccount::cases(),
        ]);
    }

    public function destroy(Bill $bill, DeleteDraftBill $deleteDraft): RedirectResponse
    {
        Gate::authorize('draftBills', $bill->matter);

        try {
            $deleteDraft->delete($bill);
        } catch (BillRefused $e) {
            return redirect()->route('bill.show', $bill)->withErrors(['bill' => $e->problems], 'bill');
        }

        return redirect()->to(BillingHome::url($bill->matter))->with('status', $bill->kind === BillKind::AgreementFee
            ? 'Draft deleted. The fee can be billed again from the agreement.'
            : 'Draft deleted. Its work is free to bill again.');
    }
}
