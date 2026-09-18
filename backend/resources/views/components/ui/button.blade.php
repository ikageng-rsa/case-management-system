@props([
    'variant' => 'primary',
    'size' => null,
    'href' => null,
    'icon' => null,
])

@php
    $classes = [
        'btn',
        'btn-' . $variant => filled($variant),
        'btn-lg' => $size === 'lg',
        'btn-sm' => $size === 'sm',
        'btn-icon' => $icon && $slot->isEmpty(),
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)
            <x-ui.icon :name="$icon" />
        @endif
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->class($classes)->merge(['type' => 'button']) }}>
        @if ($icon)
            <x-ui.icon :name="$icon" />
        @endif
        {{ $slot }}
    </button>
@endif
