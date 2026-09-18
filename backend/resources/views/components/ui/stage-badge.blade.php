@props([
    'tone' => 'neutral',
])

<span {{ $attributes->class(['badge-stage', 'badge-stage-' . $tone]) }}>{{ $slot }}</span>
