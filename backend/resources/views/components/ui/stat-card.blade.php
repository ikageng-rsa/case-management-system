@props([
    'label',
    'value',
    'delta' => null,
    'tone' => 'success',
])

<div {{ $attributes->class(['card', 'stat-card']) }}>
    <div class="card-body">
        <div class="stat-card-label">{{ $label }}</div>

        <div class="d-flex align-items-baseline gap-2">
            <span class="stat-card-value">{{ $value }}</span>

            @if ($delta)
                <span class="stat-card-delta text-{{ $tone }}">{{ $delta }}</span>
            @endif
        </div>
    </div>
</div>
