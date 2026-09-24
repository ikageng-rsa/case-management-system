@props([
    'placeholder' => 'Search clients, references, narrations…',
    'search' => true,
])

<header {{ $attributes->class(['navbar', 'app-header']) }}>
    @if ($search)
        <x-ui.search-field :placeholder="$placeholder" wrapper="d-none d-md-flex" />
    @endif

    <div class="app-header-actions">
        {{ $slot }}
    </div>
</header>
