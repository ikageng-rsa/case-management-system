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
                        <tr class="cursor-pointer" tabindex="0" role="button" x-data
                            x-on:click="$dispatch('sheet-open', { name: 'audit-entry', record: @js($details[$activity->getKey()]) })"
                            x-on:keydown.enter="$dispatch('sheet-open', { name: 'audit-entry', record: @js($details[$activity->getKey()]) })">
                            <td class="text-meta text-nowrap">{{ $activity->created_at->format('d M H:i') }}</td>
                            @if ($activity->causer)
                                <td>{{ $activity->causer->name }}</td>
                            @else
                                <td class="text-meta text-secondary">—</td>
                            @endif
                            <td @class(['text-meta', $tone($activity->event)])>{{ str_replace('_', ' ', $activity->event) }}</td>
                            <td class="text-meta">{{ $subject?->auditLabel() ?? $activity->log_name.'#'.$activity->subject_id }}</td>
                            @if ($matter)
                                <td class="text-meta"><a href="{{ route('matter.show', $matter) }}" x-on:click.stop>{{ $matter->reference }}</a></td>
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

    <x-ui.sheet name="audit-entry" title="Audit entry" wide>
        <x-slot:header>
            <div>
                <h2 class="offcanvas-title mb-1">Audit entry</h2>
                <p class="text-meta mb-0" x-text="record.when"></p>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </x-slot:header>

        <template x-if="record.when">
            <div>
                <p class="mb-4" x-text="record.description"></p>

                <dl class="row mb-4">
                    <dt class="col-sm-3 text-meta text-uppercase">Actor</dt>
                    <dd class="col-sm-9" x-text="record.actor"></dd>

                    <dt class="col-sm-3 text-meta text-uppercase">Action</dt>
                    <dd class="col-sm-9" x-text="record.event"></dd>

                    <dt class="col-sm-3 text-meta text-uppercase">Subject</dt>
                    <dd class="col-sm-9">
                        <span x-text="record.subject"></span>
                        <span class="text-secondary" x-show="record.subjectDeleted"> · no longer exists</span>
                    </dd>

                    <dt class="col-sm-3 text-meta text-uppercase">Log</dt>
                    <dd class="col-sm-9" x-text="record.log"></dd>

                    <template x-if="record.matter">
                        <dt class="col-sm-3 text-meta text-uppercase">Matter</dt>
                    </template>
                    <template x-if="record.matter">
                        <dd class="col-sm-9"><a x-bind:href="record.matterUrl" x-text="record.matter"></a></dd>
                    </template>

                    <template x-if="record.ip">
                        <dt class="col-sm-3 text-meta text-uppercase">IP</dt>
                    </template>
                    <template x-if="record.ip">
                        <dd class="col-sm-9" x-text="record.ip"></dd>
                    </template>
                </dl>

                <template x-if="record.changes.length">
                    <div class="mb-4">
                        <h3 class="text-meta text-uppercase mb-2">What changed</h3>
                        <table class="table table-vcenter table-sm">
                            <thead>
                                <tr>
                                    <th>Field</th>
                                    <th>Before</th>
                                    <th>After</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="change in record.changes" :key="change.field">
                                    <tr>
                                        <td class="text-meta" x-text="change.field"></td>
                                        <td class="text-secondary" x-text="change.old"></td>
                                        <td x-text="change.new"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>

                <template x-if="record.properties.length">
                    <div>
                        <h3 class="text-meta text-uppercase mb-2">Details</h3>
                        <dl class="row mb-0">
                            <template x-for="property in record.properties" :key="property.key">
                                <div class="row g-0">
                                    <dt class="col-sm-3 text-meta text-uppercase" x-text="property.key"></dt>
                                    <dd class="col-sm-9" x-text="property.value"></dd>
                                </div>
                            </template>
                        </dl>
                    </div>
                </template>
            </div>
        </template>

        <x-slot:footer>
            <x-ui.button variant="outline-secondary" x-on:click="close()">Close</x-ui.button>
        </x-slot:footer>
    </x-ui.sheet>
@endsection
