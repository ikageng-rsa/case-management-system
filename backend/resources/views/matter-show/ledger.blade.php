@php
    $link = fn (array $params = []) => route('matter.show', [$matter, 'tab' => 'ledger'] + $params);
    $quantity = fn ($narration) => rtrim(rtrim((string) $narration->quantity, '0'), '.');
@endphp

<div class="card">
    <div class="card-header d-block">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <div>
                <h3 class="card-title mb-0">Attendance ledger</h3>
                <p class="text-meta mb-0">{{ $allCount }} {{ Str::plural('entry', $allCount) }} · {{ number_format($minutesRecorded / 60, 1) }} h recorded</p>
            </div>

            <x-ui.button variant="outline-secondary" size="sm"
                :href="route('matter.narrations.export', array_filter([$matter, 'filter' => $filter]))">Export</x-ui.button>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2 bg-light rounded-pill p-1">
            <x-ui.button :variant="blank($filter) ? 'dark' : 'ghost-secondary'" class="rounded-pill" size="sm"
                :href="$link()">all {{ $allCount }}</x-ui.button>

            @foreach ($filters as $code => $type)
                <x-ui.button :variant="$filter === $code ? 'dark' : 'ghost-secondary'" class="rounded-pill" size="sm"
                    :href="$link(['filter' => $code])">{{ $type['label'] }} {{ $type['count'] }}</x-ui.button>
            @endforeach

            <x-ui.button :variant="$filter === 'mine' ? 'dark' : 'ghost-secondary'" class="rounded-pill" size="sm"
                :href="$link(['filter' => 'mine'])">mine {{ $mineCount }}</x-ui.button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>By</th>
                    <th>Attendance</th>
                    <th>Quantity</th>
                    <th>Units</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($narrations as $narration)
                    <tr>
                        <td class="text-meta text-nowrap">{{ $narration->occurred_at->format('d M H:i') }}</td>
                        <td class="text-meta text-secondary">{{ $narration->activityType->name }}</td>
                        <td class="text-meta">{{ Str::initials($narration->author->name, capitalize: true) }}</td>
                        <td>{{ $narration->body }}</td>
                        <td class="text-secondary text-nowrap">{{ $quantity($narration) }} {{ $narration->activityType->measure->value }}</td>
                        <td class="text-secondary">{{ $narration->billableUnits() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-secondary py-4">No narrations to show.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
