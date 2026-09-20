@props([
    'firm' => null,
    'tagline' => null,
    'href' => '/',
    'user' => null,
    'role' => null,
    'initials' => null,
    'id' => 'sidebar-menu',
])

<aside {{ $attributes->class(['navbar', 'navbar-vertical', 'navbar-expand-lg'])->merge(['data-bs-theme' => 'dark']) }}>
    <div class="container-fluid">
        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="offcanvas"
            data-bs-target="#{{ $id }}"
            aria-controls="{{ $id }}"
            aria-label="Toggle navigation"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div
            class="offcanvas-lg offcanvas-start d-lg-flex flex-lg-column flex-lg-grow-1"
            tabindex="-1"
            id="{{ $id }}"
            aria-label="Main navigation"
        >
            <div class="offcanvas-body d-flex flex-column flex-lg-grow-1">
                <div class="navbar-brand navbar-brand-autodark m-0">
                    <a href="{{ $href }}" class="d-flex align-items-center gap-2 text-decoration-none">
                        <x-ui.logo-mark size="sm" :initial="Str::substr($firm ?? 'N', 0, 1)" />

                        <span>
                            <span class="app-sidebar-firm-name d-block">{{ $firm }}</span>
                            <span class="app-sidebar-firm-sub d-block">{{ $tagline }}</span>
                        </span>
                    </a>
                </div>

                <ul class="navbar-nav pt-lg-3">
                    {{ $slot }}
                </ul>

                @if ($user)
                    <div class="navbar-footer mt-auto d-flex align-items-center gap-2">
                        <span class="sidebar-avatar">{{ $initials }}</span>

                        <span>
                            <span class="app-sidebar-user-name d-block">{{ $user }}</span>
                            <span class="app-sidebar-user-role d-block">{{ $role }}</span>
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</aside>
