@extends('layouts.app')

@section('title', 'Matters — Example Firm')

@section('content')
    <div class="d-flex align-items-start justify-content-between mb-1">
        <div>
            <h1 class="mb-1">Matters</h1>
            <p class="text-secondary mb-4">{{ $counts['open'] }} open · {{ $counts['unassigned'] }} unassigned · {{ $counts['closed_this_year'] }} closed this year</p>
        </div>
        
         <x-ui.button variant="primary" icon="plus" size="sm"
            x-data x-on:click="$dispatch('sheet-open', 'new-matter')">New Matter</x-ui.button>
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

    <x-ui.sheet name="new-matter" title="Open a new matter" wide>
        <x-slot:header>
            <div class="d-flex align-items-start justify-content-between w-100">
                <div>
                    <h2 class="offcanvas-title mb-1">Open a new matter</h2>
                    <p class="text-meta mb-0">step 2 of 3 · matter details</p>
                </div>

                <div class="d-flex gap-1 mt-2">
                    <span class="bg-primary" style="width: 20px; height: 3px; border-radius: 999px;"></span>
                    <span class="bg-primary" style="width: 20px; height: 3px; border-radius: 999px;"></span>
                    <span class="bg-light" style="width: 20px; height: 3px; border-radius: 999px;"></span>
                </div>
            </div>
        </x-slot:header>

        <div class="d-flex align-items-start justify-content-between gap-3 p-3 mb-4 rounded"
             style="background-color: var(--cms-ink-900);">
            <div>
                <p class="text-uppercase mb-1" style="font-family: var(--cms-font-mono); font-size: var(--cms-text-2xs); letter-spacing: var(--cms-tracking-label-wide); color: var(--cms-gold-500);">
                    Reference to be issued
                </p>
                <p class="mb-0" style="font-family: var(--cms-font-mono); font-size: var(--cms-text-xl); color: #fff;">
                    NGA<span style="color: var(--cms-gold-500);"> / 2026 </span>/ LAB<span style="color: var(--cms-gold-500);"> / 0135</span>
                </p>
            </div>

            <p class="text-end mb-0 fst-italic" style="font-size: var(--cms-text-xs); color: var(--cms-on-ink-caption); max-width: 220px;">
                Firm code · year · matter-type code · sequence. Reserved by the API on save; the sequence you see is provisional.
            </p>
        </div>

        <form>
            <x-ui.input
                label="Client"
                name="client"
                before="Existing client or create new"
                hint="2 existing matters — check for duplicates"
                value="Mokoena, Thabo · 072 ··· 4418"
            />

            <x-ui.select
                label="Matter type"
                name="matter_type"
                before="Drives the reference code"
                hint="code LAB"
            >
                <option>Labour</option>
                <option>Criminal</option>
                <option>Matrimonial</option>
            </x-ui.select>

            <x-ui.select
                label="Sub-type"
                name="sub_type"
                before="For reporting only"
            >
                <option>Unfair dismissal</option>
                <option>Retrenchment</option>
                <option>Misconduct</option>
            </x-ui.select>

            <x-ui.input
                label="Matter title"
                name="matter_title"
                before="Appears on the file cover"
                value="Mokoena, T v Rustenburg Platinum Mines (Pty) Ltd"
            />

            <x-ui.select
                label="Responsible attorney"
                name="attorney"
                before="Who owns the file"
            >
                <option>L. Radebe</option>
                <option>P. Mahlangu</option>
            </x-ui.select>

            <x-ui.input
                label="Date of instruction"
                name="instructed_at"
                before="prescription clock starts here"
                value="14 / 08 / 2026"
            />

            <x-ui.input
                label="Opening narration"
                name="narration"
                before="Optional first ledger entry"
                value="Instruction received; mandate signed and POPIA consent captured."
            />
        </form>

        <div class="d-flex align-items-center justify-content-between">
            <x-ui.button variant="outline-secondary">
                <x-ui.icon name="arrow-left" class="me-1" /> Back
            </x-ui.button>

            <div class="d-flex gap-2">
                <x-ui.button variant="outline-secondary">Save draft</x-ui.button>
                <x-ui.button variant="primary">Continue to parties →</x-ui.button>
            </div>
        </div>
    </x-ui.sheet>
@endsection
