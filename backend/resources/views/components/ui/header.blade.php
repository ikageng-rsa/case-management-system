@props([
    'placeholder' => 'Search clients, references, narrations…',
    'search' => true,
])

<header {{ $attributes->class(['app-header']) }}>
    @if ($search)
        <x-ui.search-field :placeholder="$placeholder" />
    @endif

    <div class="app-header-actions">
        {{ $slot }}
    </div>
</header>
