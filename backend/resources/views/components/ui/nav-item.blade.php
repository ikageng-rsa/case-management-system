@props([
    'href' => '#',
    'label' => null,
    'count' => null,
    'icon' => null,
    'active' => false,
])

<a
    href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    {{ $attributes->class(['sidebar-nav-item', 'active' => $active]) }}
>
    <span class="d-flex align-items-center gap-2">
        @if ($icon)
            <x-ui.icon :name="$icon" />
        @endif
        {{ $label ?? $slot }}
    </span>

    @if (! is_null($count))
        <span class="sidebar-nav-count">{{ $count }}</span>
    @endif
</a>
