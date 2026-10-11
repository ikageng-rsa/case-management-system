@extends('layouts.app')

@section('title', 'Dashboard — Example Firm')

@section('content')
    @php
        $greeting = match (true) {
            now()->hour < 12 => 'Good morning',
            now()->hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };

        $openMattersDelta = "+{$openedThisWeekCount} this week";
        $awaitingActionDelta = "{$overdueCount} overdue";
        $unbilledHoursDelta = $unbilledEntriesCount.' '.Str::plural('entry', $unbilledEntriesCount);
        $newIntakesDelta = "{$unassignedIntakesCount} unassigned";
    @endphp
    <h1 class="mb-1">{{ $greeting }}, {{ Str::before(auth()->user()->name, ' ') }}</h1>
    <p class="text-secondary mb-4">{{ now()->format('l d F Y') }}</p>

    <div class="row row-cards mb-4">
        <div class="col-sm-6 col-lg-3">
            <x-ui.stat-card label="Open matters" :value="$openMattersCount" :delta="$openMattersDelta" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-ui.stat-card
                label="Awaiting my action"
                :value="$awaitingActionCount"
                :delta="$awaitingActionDelta"
                tone="danger"
            />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-ui.stat-card
                label="Unbilled hours"
                :value="number_format($unbilledHours, 1)"
                :delta="$unbilledHoursDelta"
                tone="secondary"
            />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-ui.stat-card
                label="New intakes"
                :value="$newIntakesCount"
                :delta="$newIntakesDelta"
                tone="warning"
            />
        </div>
    </div>

    <div class="row row-cards">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0">Matters needing attention</h3>
                    <a href="#" class="ms-auto text-meta">view all</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Client</th>
                                <th>Status</th>
                                <th>Next date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($mattersNeedingAttention as $row)
                                <tr>
                                    <td class="text-meta">{{ $row['matter']->reference }}</td>
                                    <td>{{ $row['matter']->client?->full_name ?? '—' }}</td>
                                    <td><x-ui.stage-badge :tone="$row['tone']">{{ $row['label'] }}</x-ui.stage-badge></td>
                                    <td>{{ $row['nextDate']?->format('d M') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-secondary">No matters need attention right now.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0">Diary — today</h3>
                </div>
                <div class="card-body">
                    @forelse ($diaryToday as $entry)
                        <div class="mb-3">
                            <p class="text-meta mb-0">{{ $entry->due_at->format('H:i') }}</p>
                            <p class="mb-0">{{ $entry->body }}</p>
                            <p class="text-meta mb-0">{{ $entry->matter->reference }}</p>
                        </div>
                    @empty
                        <p class="text-secondary mb-0">Nothing on the diary for today.</p>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Recent narration</h3>
                </div>
                <div class="card-body">
                    @forelse ($recentNarrations as $narration)
                        <p class="mb-1">{{ $narration->body }}</p>
                        <p class="text-meta {{ $loop->last ? 'mb-0' : 'mb-3' }}">
                            {{ $narration->author->name }} · {{ $narration->occurred_at->diffForHumans() }}
                        </p>
                    @empty
                        <p class="text-secondary mb-0">No narration recorded yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
