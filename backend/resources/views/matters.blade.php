@extends('layouts.app')

@section('title', 'Matters — Example Firm')

@section('content')
    <div class="d-flex align-items-start justify-content-between mb-1">
        <div>
            <h1 class="mb-1">Matters</h1>
            <p class="text-secondary mb-4">{{ $counts['open'] }} open · {{ $counts['unassigned'] }} unassigned · {{ $counts['closed_this_year'] }} closed this year</p>
        </div>
        
        @can('create', \App\Models\Matter::class)
            <x-ui.button variant="primary" icon="plus" size="sm"
                x-data x-on:click="$dispatch('sheet-open', 'new-matter')">New Matter</x-ui.button>
        @endcan
    </div>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        @php
            $pills = [
                'all' => 'All '.$counts['open'],
                'mine' => 'Mine '.$counts['mine'],
                'unassigned' => 'Unassigned '.$counts['unassigned'],
                'closed' => 'Closed '.$counts['closed'],
            ];
            $sorts = ['next_date' => 'next date', 'opened' => 'date opened', 'reference' => 'reference'];
            $query = fn (array $overrides) => array_filter(
                array_merge(['filter' => $filter, 'sort' => $sort, 'type' => $matterTypeId], $overrides),
            );
        @endphp

        <div class="d-flex align-items-center gap-2 bg-light rounded-pill p-1" role="group" aria-label="Matter filters">
            @foreach ($pills as $key => $label)
                <x-ui.button :variant="$filter === $key ? 'dark' : 'ghost-secondary'" class="rounded-pill" size="sm"
                    :href="route('matters', $query(['filter' => $key]))">{{ $label }}</x-ui.button>
            @endforeach
        </div>

        <div class="d-flex align-items-center gap-2 bg-light rounded-pill p-1">
            <div class="dropdown position-relative" x-data="{ open: false }" x-on:click.outside="open = false">
                <x-ui.button variant="ghost-secondary" class="rounded-pill" size="sm" x-on:click="open = ! open">
                    type: {{ $matterTypes->firstWhere('id', $matterTypeId)?->name ?? 'all' }}
                    <x-ui.icon name="chevron-down" class="ms-1" />
                </x-ui.button>
                <div class="dropdown-menu" x-bind:class="{ show: open }">
                    <a class="dropdown-item" href="{{ route('matters', $query(['type' => null])) }}">All</a>
                    @foreach ($matterTypes as $type)
                        <a class="dropdown-item" href="{{ route('matters', $query(['type' => $type->id])) }}">{{ $type->name }}</a>
                    @endforeach
                </div>
            </div>

            <x-ui.button variant="ghost-secondary" class="rounded-pill" size="sm">
                stage: all <x-ui.icon name="chevron-down" class="ms-1" />
            </x-ui.button>

            <div class="dropdown position-relative" x-data="{ open: false }" x-on:click.outside="open = false">
                <x-ui.button variant="ghost-secondary" class="rounded-pill" size="sm" x-on:click="open = ! open">
                    sort: {{ $sorts[$sort] }} <x-ui.icon name="chevron-down" class="ms-1" />
                </x-ui.button>
                <div class="dropdown-menu" x-bind:class="{ show: open }">
                    @foreach ($sorts as $key => $label)
                        <a class="dropdown-item" href="{{ route('matters', $query(['sort' => $key])) }}">{{ $label }}</a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Client</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Attorney</th>
                        <th>Next date</th>
                        <th>Docs</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($matters as $matter)
                        @php
                            $attorney = $matter->responsibleAttorney();
                            $nextDate = $matter->diaryEntries->sortBy('due_at')->first()?->due_at;
                        @endphp
                        <tr>
                            <td class="text-meta"><a href="{{ route('matter.show', $matter) }}">{{ $matter->reference }}</a></td>
                            <td>{{ $matter->client->full_name }}</td>
                            <td class="text-secondary">{{ $matter->matterType->name }}</td>
                            <td>
                                @if ($matter->isClosed())
                                    <x-ui.stage-badge tone="success">Closed</x-ui.stage-badge>
                                @else
                                    <x-ui.stage-badge tone="info">Open</x-ui.stage-badge>
                                @endif
                            </td>
                            @if ($attorney)
                                <td>{{ $attorney->name }}</td>
                            @else
                                <td class="text-secondary">Unassigned</td>
                            @endif
                            @if ($nextDate)
                                <td @class(['text-danger' => $nextDate->isPast()])>{{ $nextDate->format('d M') }}</td>
                            @else
                                <td class="text-secondary">—</td>
                            @endif
                            <td class="text-secondary">{{ $matter->media_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-secondary py-4">No matters to show.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer d-flex align-items-center justify-content-between">
            <span class="text-meta">showing {{ $matters->count() }} of {{ $matters->total() }}</span>
            @if ($matters->hasMorePages())
                <a href="{{ $matters->nextPageUrl() }}" class="text-meta">load more →</a>
            @endif
        </div>
    </div>

    @can('create', \App\Models\Matter::class)
        <x-matter.intake name="new-matter" :matter-types="$matterTypes" :courts="$courts" :attorneys="$attorneys"
            :selected-client="$selectedClient" :action="route('matter.store')" />
    @endcan
@endsection
