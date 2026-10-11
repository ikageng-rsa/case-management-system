@extends('layouts.app')

@section('title', 'Audit log — Example Firm')

@section('content')
    @php
        $query = fn (array $overrides) => array_filter(array_merge(['actor' => $actor, 'action' => $action], $overrides));

        $tone = fn (?string $event) => match ($event) {
            'created', 'uploaded', 'role_granted' => 'text-success',
            'updated' => 'text-info',
            'deleted', 'role_revoked' => 'text-danger',
            'exported', 'downloaded' => 'text-primary',
            default => 'text-secondary',
        };
    @endphp

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-1">
        <div>
            <h1 class="mb-1">Audit log</h1>
            <p class="text-secondary mb-4">append-only · 7-year retention · no delete endpoint exists</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <div class="dropdown position-relative" x-data="{ open: false }" x-on:click.outside="open = false">
                <x-ui.button variant="outline-secondary" size="sm" x-on:click="open = ! open">
                    actor: {{ $actors->firstWhere('id', $actor)?->name ?? 'all' }}
                    <x-ui.icon name="chevron-down" class="ms-1" />
                </x-ui.button>
                <div class="dropdown-menu dropdown-menu-end" x-bind:class="{ show: open }">
                    <a class="dropdown-item" href="{{ route('audit-log', $query(['actor' => null])) }}">All</a>
                    @foreach ($actors as $person)
                        <a class="dropdown-item" href="{{ route('audit-log', $query(['actor' => $person->getKey()])) }}">{{ $person->name }}</a>
                    @endforeach
                </div>
            </div>

            <div class="dropdown position-relative" x-data="{ open: false }" x-on:click.outside="open = false">
                <x-ui.button variant="outline-secondary" size="sm" x-on:click="open = ! open">
                    action: {{ $action ? str_replace('_', ' ', $action) : 'all' }}
                    <x-ui.icon name="chevron-down" class="ms-1" />
                </x-ui.button>
                <div class="dropdown-menu dropdown-menu-end" x-bind:class="{ show: open }">
                    <a class="dropdown-item" href="{{ route('audit-log', $query(['action' => null])) }}">All</a>
                    @foreach ($actions as $event)
                        <a class="dropdown-item" href="{{ route('audit-log', $query(['action' => $event])) }}">{{ str_replace('_', ' ', $event) }}</a>
                    @endforeach
                </div>
            </div>

            @if ($canExport)
                <x-ui.button variant="outline-secondary" size="sm" :href="route('audit-log.export', $query([]))">export csv</x-ui.button>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Actor</th>
                        <th>Action</th>
                        <th>Subject</th>
                        <th>Matter</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($activities as $activity)
                        @php
                            $subject = $activity->subject;
                            $matter = $matterOf($activity);
                        @endphp
                        <tr>
                            <td class="text-meta text-nowrap">{{ $activity->created_at->format('d M H:i') }}</td>
                            @if ($activity->causer)
                                <td>{{ $activity->causer->name }}</td>
                            @else
                                <td class="text-meta text-secondary">—</td>
                            @endif
                            <td @class(['text-meta', $tone($activity->event)])>{{ str_replace('_', ' ', $activity->event) }}</td>
                            <td class="text-meta">{{ $subject?->auditLabel() ?? $activity->log_name.'#'.$activity->subject_id }}</td>
                            @if ($matter)
                                <td class="text-meta"><a href="{{ route('matter.show', $matter) }}">{{ $matter->reference }}</a></td>
                            @else
                                <td class="text-meta text-secondary">—</td>
                            @endif
                            <td class="text-meta">{{ $activity->properties['ip'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-secondary py-4">Nothing recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($activities->hasPages())
            <div class="card-footer d-flex align-items-center justify-content-between">
                <span class="text-meta">showing {{ $activities->firstItem() }}–{{ $activities->lastItem() }} of {{ $activities->total() }}</span>
                <div class="d-flex gap-3">
                    @if (! $activities->onFirstPage())
                        <a href="{{ $activities->previousPageUrl() }}" class="text-meta">← newer</a>
                    @endif
                    @if ($activities->hasMorePages())
                        <a href="{{ $activities->nextPageUrl() }}" class="text-meta">older →</a>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection
