@extends('layouts.app')

@section('title', $schedule->name.' — Example Firm')

@section('content')
    @php
        $rand = fn (?int $cents) => $cents === null ? '—' : 'R '.number_format($cents / 100, 2, '.', ' ');
    @endphp

    <div class="mb-3 d-flex align-items-start justify-content-between">
        <div>
            <a href="{{ route('reference-data', ['tab' => 'tariffs']) }}" class="text-meta">← Tariffs</a>
            <h1 class="mb-1">{{ $schedule->name }}</h1>
            <p class="text-secondary mb-0">
                In force from {{ $schedule->effective_from->format('j F Y') }}
                @if ($inForce)
                    <x-ui.stage-badge tone="success" class="ms-2">In force</x-ui.stage-badge>
                @elseif (! $schedule->hasStarted())
                    <x-ui.stage-badge tone="info" class="ms-2">Upcoming</x-ui.stage-badge>
                @else
                    <x-ui.stage-badge tone="neutral" class="ms-2">Replaced</x-ui.stage-badge>
                @endif
            </p>
        </div>

        <x-ui.button :href="route('tariff-schedule.source', $schedule)" variant="outline-secondary" icon="download" size="sm">Download file</x-ui.button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Kind of work</th>
                        <th>Scale</th>
                        <th>Role</th>
                        <th class="text-end">Rate</th>
                        <th class="text-end">Time on top</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td>
                                <span class="text-meta me-2">{{ $item->activityType->code }}</span>{{ $item->activityType->name }}
                            </td>
                            <td>{{ $item->scale?->value ?? 'Any' }}</td>
                            <td>{{ $item->role ? Str::headline($item->role->name) : 'Any' }}</td>
                            <td class="text-end text-nowrap">{{ $rand($item->unit_rate_cents) }} <span class="text-meta">{{ $item->activityType->increment === 1 ? $item->activityType->unit_label : 'per '.$item->activityType->unit_label }}</span></td>
                            <td class="text-end text-nowrap">
                                @if ($item->chargesTime())
                                    {{ $rand($item->time_rate_cents) }} <span class="text-meta">per {{ $item->time_block_minutes }} minutes</span>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-secondary py-4">This tariff has no rates.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
