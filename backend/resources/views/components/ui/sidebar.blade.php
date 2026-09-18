@props([
    'firm' => null,
    'tagline' => null,
    'href' => '/',
    'user' => null,
    'role' => null,
    'initials' => null,
])

<aside {{ $attributes->class(['app-sidebar']) }}>
    <a href="{{ $href }}" class="app-sidebar-brand">
        <x-ui.logo-mark size="sm" :initial="Str::substr($firm ?? 'N', 0, 1)" />

        <span>
            <span class="app-sidebar-firm-name d-block">{{ $firm }}</span>
            <span class="app-sidebar-firm-sub d-block">{{ $tagline }}</span>
        </span>
    </a>

    <nav class="app-sidebar-nav">
        {{ $slot }}
    </nav>

    @if ($user)
        <div class="app-sidebar-footer">
            <span class="sidebar-avatar">{{ $initials }}</span>

            <span>
                <span class="app-sidebar-user-name d-block">{{ $user }}</span>
                <span class="app-sidebar-user-role d-block">{{ $role }}</span>
            </span>
        </div>
    @endif
</aside>
