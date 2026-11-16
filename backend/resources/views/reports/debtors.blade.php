{{-- What is owed on issued bills, aged by days since issue. Clients and the other side are kept apart. --}}
@php
    $rand = fn (int $cents) => 'R '.number_format($cents / 100, 2, '.', ' ');
    $buckets = $debtors['buckets'];
    $oldest = $buckets[array_key_last($buckets)];
    $sections = [
        'Owed by clients' => ['data' => $debtors['owedByClients'], 'lede' => 'Statements and fee bills issued to clients, less what they have paid.', 'empty' => 'No client owes anything on an issued bill.'],
        'Owed on bills of cost' => ['data' => $debtors['owedOnBillsOfCost'], 'lede' => 'Bills of cost issued to the other side, less what has been paid.', 'empty' => 'Nothing is owed on a bill of cost.'],
    ];
@endphp

<div class="row row-cards mb-3">
    <div class="col-sm-6 col-lg-4">
        <x-ui.stat-card label="Owed by clients" :value="$rand($debtors['owedByClients']['totalCents'])" />
    </div>
    <div class="col-sm-6 col-lg-4">
        <x-ui.stat-card label="Owed on bills of cost" :value="$rand($debtors['owedOnBillsOfCost']['totalCents'])" />
    </div>
    <div class="col-sm-6 col-lg-4">
        <x-ui.stat-card label="Owed for over 90 days"
            :value="$rand($debtors['owedByClients']['bucketCents'][$oldest] + $debtors['owedOnBillsOfCost']['bucketCents'][$oldest])" />
    </div>
</div>

@foreach ($sections as $title => $section)
    <div class="card mb-3">
        <div class="card-header">
            <div>
                <h3 class="card-title mb-0">{{ $title }}</h3>
                <p class="text-meta mb-0">{{ $section['lede'] }}</p>
            </div>
        </div>

        @if ($section['data']['rows']->isEmpty())
            <div class="card-body">
                <p class="text-secondary mb-0">{{ $section['empty'] }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Matter</th>
                            <th>Addressed to</th>
                            <th>Issued</th>
                            @foreach ($buckets as $bucket)
                                <th class="text-end">{{ $bucket }}</th>
                            @endforeach
                            <th class="text-end">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($section['data']['rows'] as $row)
                            <tr>
                                <td class="text-nowrap">
                                    @can('viewBills', $row['bill']->matter)
                                        <a href="{{ route('bill.show', [$row['bill'], 'from' => 'debtors']) }}">{{ $row['bill']->number }}</a>
                                    @else
                                        {{ $row['bill']->number }}
                                    @endcan
                                    <div class="text-meta">{{ $row['bill']->kind->label() }}</div>
                                </td>
                                <td class="text-nowrap">
                                    <a href="{{ route('matter.show', [$row['bill']->matter, 'tab' => 'billing']) }}">{{ $row['bill']->matter->reference }}</a>
                                </td>
                                <td>{{ $row['bill']->addressee }}</td>
                                <td class="text-nowrap">
                                    {{ $row['bill']->issued_at->format('j M Y') }}
                                    <div class="text-meta">{{ $row['days'] }} {{ Str::plural('day', $row['days']) }} ago</div>
                                </td>
                                @foreach ($buckets as $bucket)
                                    <td class="text-end text-nowrap">{{ $row['bucket'] === $bucket ? $rand($row['balanceCents']) : '' }}</td>
                                @endforeach
                                <td class="text-end text-nowrap">
                                    {{ $rand($row['balanceCents']) }}
                                    @if ($row['bill']->paid_cents > 0)
                                        <div class="text-meta">of {{ $rand($row['bill']->total_cents) }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-end"><strong>Total</strong></td>
                            @foreach ($buckets as $bucket)
                                <td class="text-end text-nowrap"><strong>{{ $rand($section['data']['bucketCents'][$bucket]) }}</strong></td>
                            @endforeach
                            <td class="text-end text-nowrap"><strong>{{ $rand($section['data']['totalCents']) }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
@endforeach
