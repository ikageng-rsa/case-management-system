@props([
    'initial' => 'N',
    'size' => null,
])

<span {{ $attributes->class(['logo-mark', 'logo-mark-sm' => $size === 'sm']) }}>
    {{ $slot->isNotEmpty() ? $slot : $initial }}
</span>
