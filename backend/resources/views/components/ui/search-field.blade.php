@props([
    'placeholder' => 'Search…',
    'shortcut' => '⌘K',
    'wrapper' => null,
])

<div @class(array_filter(['search-field', $wrapper]))>
    <x-ui.icon name="search" class="search-field-icon" />

    <input
        type="search"
        {{ $attributes->class(['search-field-input'])->merge(['placeholder' => $placeholder]) }}
    >

    @if ($shortcut)
        <span class="search-field-shortcut">{{ $shortcut }}</span>
    @endif
</div>
