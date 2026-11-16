<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Enums\Auth\Permission;
use App\Enums\Billing\BillKind;
use App\Enums\Billing\FirmAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StorePaymentRequest;
use App\Models\Billing\Bill;
use App\Models\User\User;
use App\Queries\Billing\PaymentList;
use App\Services\Billing\EmailPaymentToClient;
use App\Services\Billing\PaymentRefused;
use App\Services\Billing\RecordPayment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class PaymentsController extends Controller
{
    public function index(Request $request, PaymentList $paymentList): View
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->can(Permission::ViewPayments->value), 403);

        $from = $this->day($request->query('from'));
        $to = $this->day($request->query('to'));
        $account = FirmAccount::tryFrom((string) $request->query('account'));

        return view('payments', [
            'from' => $from,
            'to' => $to,
            'account' => $account,
            'accounts' => FirmAccount::cases(),
        ] + $paymentList->for($user, $from, $to, $account));
    }

    protected function day(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! Carbon::canBeCreatedFromFormat($value, 'Y-m-d')) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
    }

    public function store(StorePaymentRequest $request, Bill $bill, RecordPayment $recordPayment, EmailPaymentToClient $emailClient): RedirectResponse
    {
        Gate::authorize('recordPayments', $bill->matter);

        $back = redirect()->route('bill.show', $bill);

        try {
            $payment = $recordPayment->record($bill, $request->payment(), $request->user(), $request->proof());
        } catch (PaymentRefused $e) {
            return $back->withInput()->withErrors(['amount' => $e->getMessage()], 'payment');
        }

        $status = $payment->bill->balanceCents() === 0 ? 'Payment recorded. The bill is settled.' : 'Payment recorded.';

        // A bill of cost is paid by the other side, so there is no client to tell.
        if ($request->emailClient() && $bill->kind !== BillKind::BillOfCost) {
            $status .= ' '.$emailClient->send($payment)->message();
        }

        return $back->with('status', $status);
    }
}
