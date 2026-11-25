@extends('layouts.app')

@section('title', 'Payments')

@section('content')
    @php
        $rand = fn (int $cents) => 'R '.number_format($cents / 100, 2, '.', ' ');
        $filtered = $from || $to || $account;
    @endphp

    <div class="mb-3">
        <h1 class="mb-1">Payments</h1>
        <p class="text-secondary mb-0">Money received on the bills you work on, to check against the bank statement.</p>
    </div>

    <form method="GET" action="{{ route('payments') }}" class="row g-2 align-items-end mb-3">
        <div class="col-sm-auto">
            <x-ui.input label="Received from" name="from" id="payments_from" type="date" :value="$from?->toDateString()" />
        </div>
        <div class="col-sm-auto">
            <x-ui.input label="Up to" name="to" id="payments_to" type="date" :value="$to?->toDateString()" />
        </div>
        <div class="col-sm-auto">
            <x-ui.select label="Paid into" name="account" id="payments_account">
                <option value="">Either account</option>
                @foreach ($accounts as $option)
                    <option value="{{ $option->value }}" @selected($account === $option)>{{ $option->label() }}</option>
                @endforeach
            </x-ui.select>
        </div>
        <div class="col-sm-auto mb-3">
            <x-ui.button type="submit" variant="outline-secondary" size="sm">Show</x-ui.button>
            @if ($filtered)
                <x-ui.button :href="route('payments')" variant="ghost-secondary" size="sm">Clear</x-ui.button>
            @endif
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title mb-0">{{ $rand($totalCents) }} received</h3>
                <p class="text-meta mb-0">{{ $count }} {{ Str::plural('payment', $count) }}{{ $filtered ? ' matching' : '' }}. Reversed payments are listed but not counted.</p>
            </div>
        </div>

        @if ($payments->isEmpty())
            <div class="card-body">
                <p class="text-secondary mb-0">{{ $filtered ? 'No payments match.' : 'No payments recorded yet.' }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Received</th>
                            <th>Invoice</th>
                            <th>From</th>
                            <th>Paid</th>
                            <th>Recorded by</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            @php($canSeeBill = auth()->user()->can('viewBills', $payment->bill->matter))
                            <tr>
                                <td class="text-nowrap">{{ $payment->received_on->format('j M Y') }}</td>
                                <td class="text-nowrap">
                                    @if ($canSeeBill)
                                        <a href="{{ route('bill.show', $payment->bill) }}">{{ $payment->bill->number }}</a>
                                    @else
                                        {{ $payment->bill->number }}
                                    @endif
                                    <div class="text-meta">{{ $payment->bill->matter->reference }}</div>
                                </td>
                                <td>{{ $payment->bill->addressee }}</td>
                                <td>
                                    {{ $payment->method->label() }} into the {{ strtolower($payment->account->label()) }}
                                    @if ($payment->reference)
                                        <div class="text-meta">Ref {{ $payment->reference }}</div>
                                    @endif
                                </td>
                                <td>
                                    {{ $payment->recordedBy?->name ?? '—' }}
                                    @if ($canSeeBill)
                                        <div class="text-meta">
                                            @if ($payment->proof())
                                                <a href="{{ route('payment.proof', $payment) }}">Proof</a>
                                            @endif
                                            @unless ($payment->isReversed())
                                                @if ($payment->proof()) · @endif
                                                <a href="{{ route('payment.receipt', $payment) }}">Receipt</a>
                                            @endunless
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <span @class(['text-decoration-line-through text-secondary' => $payment->isReversed()])>{{ $rand($payment->amount_cents) }}</span>
                                    @if ($payment->isReversed())
                                        <div class="text-meta">Reversed {{ $payment->reversed_at->format('j M Y') }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($payments->hasPages())
        <div class="mt-3">{{ $payments->links() }}</div>
    @endif
@endsection
