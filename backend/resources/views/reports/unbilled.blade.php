@php
    $rand = fn (int $cents) => 'R '.number_format($cents / 100, 2, '.', ' ');
    $rows = $workToBill['rows']->withPath(route('reports'))->appends(['tab' => 'unbilled']);
@endphp

<div class="row row-cards mb-3">
    <div class="col-sm-6 col-lg-4">
        <x-ui.stat-card label="Unbilled value" :value="$rand($workToBill['valueCents'])" />
    </div>
    <div class="col-sm-6 col-lg-4">
        <x-ui.stat-card label="Matters to bill" :value="$rows->total()" />
    </div>
    <div class="col-sm-6 col-lg-4">
        <x-ui.stat-card label="Entries not priced" :value="$workToBill['unpricedCount']" />
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title mb-0">Work not yet on a bill</h3>
            <p class="text-meta mb-0">Billable work and disbursements recorded on a matter and not on any bill, valued on the tariff. Not an amount anyone owes yet.</p>
        </div>
    </div>

    @if ($rows->isEmpty())
        <div class="card-body">
            <p class="text-secondary mb-0">Everything recorded has been billed.</p>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Matter</th>
                        <th>Charged by</th>
                        <th>Unbilled</th>
                        <th class="text-end">Work</th>
                        <th class="text-end">Disbursements</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="text-nowrap">
                                <a href="{{ \App\Http\BillingHome::url($row['matter']) }}">{{ $row['matter']->reference }}</a>
                                <div class="text-meta">{{ $row['matter']->client->full_name }}</div>
                            </td>
                            <td>
                                @if ($row['agreement'])
                                    {{ $row['agreement']->label() }}
                                @else
                                    <span class="text-danger">No fee agreement</span>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                {{ $row['entries'] }} {{ Str::plural('entry', $row['entries']) }}
                                <div class="text-meta">since {{ $row['since']->format('j M Y') }}</div>
                            </td>
                            <td class="text-end text-nowrap">
                                {{ $rand($row['workCents']) }}
                                @if ($row['unpricedCount'] > 0)
                                    <div class="text-meta">{{ $row['unpricedCount'] }} not priced</div>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">{{ $rand($row['disbursementCents']) }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ \App\Http\BillingHome::url($row['matter']) }}" class="text-meta">Open billing</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@if ($rows->hasPages())
    <div class="mt-3">{{ $rows->links() }}</div>
@endif
