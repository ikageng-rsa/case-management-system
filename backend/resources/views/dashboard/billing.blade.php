@php
    $rand = fn (int $cents) => 'R '.number_format($cents / 100, 2, '.', ' ');
    $oldest = $debtors['buckets'][array_key_last($debtors['buckets'])];
    $longest = $debtors['owedByClients']['rows']->concat($debtors['owedOnBillsOfCost']['rows'])->sortByDesc('days')->take(5);
@endphp

<div class="row row-cards mb-4">
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

<div class="card">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title mb-0">Longest outstanding</h3>
        <a href="{{ route('reports', ['tab' => 'debtors']) }}" class="ms-auto text-meta">view all</a>
    </div>

    @if ($longest->isEmpty())
        <div class="card-body">
            <p class="text-secondary mb-0">Nothing is owed on an issued bill.</p>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Addressed to</th>
                        <th>Issued</th>
                        <th class="text-end">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($longest as $row)
                        <tr>
                            <td class="text-nowrap">
                                @can('viewBills', $row['bill']->matter)
                                    <a href="{{ route('bill.show', $row['bill']) }}">{{ $row['bill']->number }}</a>
                                @else
                                    {{ $row['bill']->number }}
                                @endcan
                                <div class="text-meta">{{ $row['bill']->kind->label() }}</div>
                            </td>
                            <td>{{ $row['bill']->addressee }}</td>
                            <td class="text-nowrap">{{ $row['days'] }} {{ Str::plural('day', $row['days']) }} ago</td>
                            <td class="text-end text-nowrap">{{ $rand($row['balanceCents']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
