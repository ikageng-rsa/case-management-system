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

        {{-- Only what the ledger holds is offered, and a row holding only "all" is just clutter. --}}
        @if ($filters->isNotEmpty() || $mineCount > 0 || $filter === 'mine')
            @php
                $typeFilter = $filters->has($filter) ? $filters[$filter] : null;
            @endphp

            {{-- The kinds of work are the firm's to name and there can be many, so they are a list to choose from
                 and not a row of buttons that outgrows the card. --}}
            <div class="d-flex flex-wrap align-items-center gap-2 bg-light rounded-pill p-1" style="--tblr-btn-font-size: var(--cms-text-base);">
                <x-ui.button :variant="blank($filter) ? 'dark' : 'ghost-secondary'" class="rounded-pill" size="sm"
                    :href="$link()">all {{ $allCount }}</x-ui.button>

                @if ($filters->isNotEmpty())
                    <div class="dropdown position-relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
                        <x-ui.button :variant="$typeFilter ? 'dark' : 'ghost-secondary'" class="rounded-pill" size="sm"
                            x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="true">
                            {{ $typeFilter ? $typeFilter['label'].' '.$typeFilter['count'] : 'type' }}
                            <x-ui.icon name="chevron-down" class="ms-1" />
                        </x-ui.button>

                        <div class="dropdown-menu" x-bind:class="{ show: open }" x-cloak style="left: 0; right: auto; max-height: 60vh; overflow-y: auto;">
                            @foreach ($filters as $code => $type)
                                <a @class(['dropdown-item d-flex align-items-center justify-content-between gap-3', 'active' => $filter === $code])
                                   href="{{ $link(['filter' => $code]) }}">
                                    <span>{{ $type['label'] }}</span>
                                    <span class="text-meta">{{ $type['count'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($mineCount > 0 || $filter === 'mine')
                    <x-ui.button :variant="$filter === 'mine' ? 'dark' : 'ghost-secondary'" class="rounded-pill" size="sm"
                        :href="$link(['filter' => 'mine'])">mine {{ $mineCount }}</x-ui.button>
                @endif
            </div>
        @endif
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
