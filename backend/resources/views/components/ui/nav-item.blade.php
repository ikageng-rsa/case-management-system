@props([
    'href' => '#',
    'label' => null,
    'count' => null,
    'icon' => null,
    'active' => false,
])

<li @class(['nav-item', 'active' => $active])>
    <a
        href="{{ $href }}"
        @if ($active) aria-current="page" @endif
        {{ $attributes->class(['nav-link']) }}
    >
        @if ($icon)
            <span class="nav-link-icon d-md-none d-lg-inline-block">
                <x-ui.icon :name="$icon" />
            </span>
        @endif

        <span class="nav-link-title">{{ $label ?? $slot }}</span>

        @if (! is_null($count))
            <span class="sidebar-nav-count ms-auto">{{ $count }}</span>
        @endif
    </a>
</li>
