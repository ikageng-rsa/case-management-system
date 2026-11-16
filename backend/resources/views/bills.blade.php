@extends('layouts.app')

@section('title', 'Bills')

@section('content')
    @php
        $rand = fn (int $cents) => 'R '.number_format($cents / 100, 2, '.', ' ');
        $tones = ['draft' => 'accent', 'issued' => 'success', 'void' => 'neutral'];
        $paymentTones = ['unpaid' => 'neutral', 'part_paid' => 'accent', 'settled' => 'success'];
    @endphp

    <div class="mb-3">
        <h1 class="mb-1">Bills</h1>
        <p class="text-secondary mb-0">Statements, bills of cost and fee bills on the matters whose billing you work on.</p>
    </div>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <ul class="nav nav-underline mb-0">
            @foreach ($showing as $key => $name)
                <li class="nav-item">
                    <a href="{{ route('bills', array_filter(['show' => $key === 'all' ? null : $key, 'q' => $search])) }}" @class(['nav-link', 'active' => $show === $key])>
                        {{ $name }} <span class="text-secondary ms-1">{{ $counts[$key] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        <form method="GET" action="{{ route('bills') }}" role="search">
            @if ($show !== 'all')
                <input type="hidden" name="show" value="{{ $show }}">
            @endif
            <x-ui.search-field name="q" :value="$search" placeholder="Invoice number, addressee or matter" :shortcut="null" aria-label="Search the bills" />
        </form>
    </div>

    <div class="card">
        @if ($bills->isEmpty())
            <div class="card-body">
                <p class="text-secondary mb-0">{{ $search !== '' ? 'No bills match that search.' : 'No bills here yet.' }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Bill</th>
                            <th>Matter</th>
                            <th>Addressed to</th>
                            <th>Date</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bills as $bill)
                            <tr>
                                <td class="text-nowrap">
                                    <a href="{{ route('bill.show', $bill) }}">{{ $bill->number ?? 'Draft' }}</a>
                                    <x-ui.stage-badge :tone="$tones[$bill->status->value]" class="ms-2">{{ $bill->status->label() }}</x-ui.stage-badge>
                                    @if ($bill->paymentState())
                                        <x-ui.stage-badge :tone="$paymentTones[$bill->paymentState()->value]" class="ms-1">{{ $bill->paymentState()->label() }}</x-ui.stage-badge>
                                    @endif
                                    <div class="text-meta">{{ $bill->kind->label() }}</div>
                                </td>
                                <td class="text-nowrap">
                                    <a href="{{ \App\Http\BillingHome::url($bill->matter) }}">{{ $bill->matter->reference }}</a>
                                    <div class="text-meta">{{ $bill->matter->client->full_name }}</div>
                                </td>
                                <td>{{ $bill->addressee }}</td>
                                <td class="text-nowrap">{{ ($bill->issued_at ?? $bill->created_at)->format('j M Y') }}</td>
                                <td class="text-end text-nowrap">{{ $rand($bill->total_cents) }}</td>
                                <td class="text-end text-nowrap">{{ $bill->isIssued() ? $rand($bill->balanceCents()) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($bills->hasPages())
        <div class="mt-3">{{ $bills->links() }}</div>
    @endif
@endsection
