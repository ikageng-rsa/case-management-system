@props([
    'name',
    'size' => null,
])

<i {{ $attributes->class([
    'ti',
    'ti-' . $name,
    'icon-sm' => $size === 'sm',
    'icon-lg' => $size === 'lg',
])->merge(['aria-hidden' => 'true']) }}></i>
