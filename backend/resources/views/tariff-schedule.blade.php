@extends('layouts.app')

@section('title', $schedule->name.' — Example Firm')

@section('content')
    @php
        $rand = fn (?int $cents) => $cents === null ? '—' : 'R '.number_format($cents / 100, 2, '.', ' ');
        $roleName = fn ($role) => Str::headline($role->name);
        $badges = [
            'draft' => ['accent', 'Draft'],
            'upcoming' => ['info', 'Upcoming'],
            'in_force' => ['success', 'In force'],
            'replaced' => ['neutral', 'Replaced'],
        ];
        $adjustmentFor = fn ($role) => $schedule->adjustments->firstWhere('role_id', $role->getKey());
        $markups = $errors->getBag('tariff_adjustments')->any() ? old('adjustments', []) : null;
    @endphp

    <div class="mb-3 d-flex align-items-start justify-content-between gap-3">
        <div>
            <a href="{{ route('reference-data', ['tab' => 'tariffs']) }}" class="text-meta">← Tariffs</a>
            <h1 class="mb-1">{{ $schedule->name }}</h1>
            <p class="text-secondary mb-0">
                In force from {{ $schedule->effective_from->format('j F Y') }}
                <x-ui.stage-badge :tone="$badges[$status][0]" class="ms-2">{{ $badges[$status][1] }}</x-ui.stage-badge>
            </p>
        </div>

        <div class="d-flex gap-2">
            <x-ui.button :href="route('tariff-schedule.source', $schedule)" variant="outline-secondary" icon="download" size="sm">Download file</x-ui.button>

            @unless ($schedule->hasStarted())
                <form method="POST" action="{{ route('tariff-schedule.destroy', $schedule) }}"
                    data-confirm="Remove {{ $schedule->name }}? It has not come into force, so nothing has been charged from it."
                    data-confirm-title="Remove tariff" data-confirm-action="Remove" data-confirm-tone="danger" data-confirm-icon="trash">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="outline-danger" size="sm">{{ $schedule->isPublished() ? 'Remove' : 'Discard draft' }}</x-ui.button>
                </form>
            @endunless

            @unless ($schedule->isPublished())
                <form method="POST" action="{{ route('tariff-schedule.publish', $schedule) }}"
                    data-confirm="Publish {{ $schedule->name }}? From {{ $schedule->effective_from->format('j F Y') }} bills are priced from these rates and markups."
                    data-confirm-title="Publish tariff" data-confirm-action="Publish" data-confirm-icon="check">
                    @csrf
                    <x-ui.button type="submit" variant="primary" icon="check" size="sm">Publish</x-ui.button>
                </form>
            @endunless
        </div>
    </div>

    @unless ($schedule->isPublished())
        <div class="alert alert-info">
            This tariff is a draft and is not used for billing yet. Check the rates read from the file and what each role charges, then publish it.
        </div>
    @endunless

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between gap-3">
                    <div>
                        <h3 class="card-title mb-0">Rates</h3>
                        <p class="text-meta mb-0">
                            @if ($asRole)
                                As {{ $roleName($asRole) }} charges them{{ $adjustmentFor($asRole) ? ' ('.$adjustmentFor($asRole)->label().')' : ', at the gazette rate' }}.
                            @else
                                As the gazette quotes them.
                            @endif
                        </p>
                    </div>

                    <form method="GET" action="{{ route('tariff-schedule.show', $schedule) }}">
                        <select name="role" class="form-select form-select-sm" aria-label="See the rates as a role" onchange="this.form.submit()">
                            <option value="">Gazette rates</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->getKey() }}" @selected($asRole?->is($role))>As {{ $roleName($role) }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Kind of work</th>
                                @foreach ($scales as $scale)
                                    <th class="text-end">Scale {{ $scale->value }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($items as $item)
                                <tr>
                                    <td>
                                        <span class="text-meta me-2">{{ $item->activityType->code }}</span>{{ $item->activityType->name }}
                                        <div class="text-meta">{{ $item->activityType->increment === 1 ? $item->activityType->unit_label : 'per '.$item->activityType->unit_label }}</div>
                                    </td>
                                    @foreach ($scales as $scale)
                                        <td class="text-end text-nowrap">{{ $rand($schedule->rateFor($item, $scale, $asRole)) }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($scales) + 1 }}" class="text-center text-secondary py-4">This tariff has no rates.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <form method="POST" action="{{ route('tariff-adjustments.update', $schedule) }}" class="card">
                @csrf
                @method('PUT')

                <div class="card-header">
                    <div>
                        <h3 class="card-title mb-0">What each role charges</h3>
                        <p class="text-meta mb-0">A markup on the gazette rate, as a percentage or in rand per unit. Use a minus for less, e.g. −50% for half.</p>
                    </div>
                </div>

                <div class="card-body">
                    @if ($errors->getBag('tariff_adjustments')->any())
                        <div class="alert alert-danger">{{ $errors->getBag('tariff_adjustments')->first() }}</div>
                    @endif

                    @foreach ($roles as $role)
                        @php
                            $adjustment = $adjustmentFor($role);
                            $kind = $markups !== null ? ($markups[$role->getKey()]['kind'] ?? '') : ($adjustment?->kind->value ?? '');
                            $value = $markups !== null ? ($markups[$role->getKey()]['value'] ?? '') : ($adjustment ? $adjustment->value / 100 : '');
                        @endphp

                        <div class="mb-3">
                            <label class="form-label form-label-mono" for="markup_{{ $role->getKey() }}">{{ $roleName($role) }}</label>
                            <div class="input-group">
                                <select name="adjustments[{{ $role->getKey() }}][kind]" class="form-select" aria-label="How {{ $roleName($role) }}'s markup is given" style="max-width: 9rem;">
                                    <option value="">No markup</option>
                                    <option value="percent" @selected($kind === 'percent')>%</option>
                                    <option value="amount" @selected($kind === 'amount')>R per unit</option>
                                </select>
                                <input type="number" step="0.01" name="adjustments[{{ $role->getKey() }}][value]" id="markup_{{ $role->getKey() }}"
                                    class="form-control text-end" value="{{ $value }}" placeholder="0">
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="card-footer text-end">
                    <x-ui.button type="submit" variant="primary" size="sm">Save markups</x-ui.button>
                </div>
            </form>
        </div>
    </div>
@endsection
