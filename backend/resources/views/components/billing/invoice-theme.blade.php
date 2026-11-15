@props([
    'theme',
])

{{--
    A drawing of how a theme lays a bill out, not a preview of a real one: bars
    for text, a rule or a band where the theme has one, and the accent colour
    where it uses it. The accent is read from --invoice-accent on a parent, so a
    colour picker can change every drawing at once.
--}}
@php
    $value = $theme instanceof \App\Enums\Billing\InvoiceTheme ? $theme->value : $theme;
    $bar = fn (string $width, string $colour, string $height = '5px') => "height: {$height}; width: {$width}; background: {$colour}; border-radius: 3px;";
@endphp

<div {{ $attributes->class(['position-relative overflow-hidden bg-white border rounded-3']) }} style="aspect-ratio: 16 / 10;" aria-hidden="true">
    @if ($value === 'modern')
        <div style="background: var(--invoice-accent); height: 30%; padding: 10% 9% 0;">
            <div style="{{ $bar('45%', 'rgba(255,255,255,.9)') }} margin-bottom: 5px;"></div>
            <div style="{{ $bar('28%', 'rgba(255,255,255,.65)') }}"></div>
        </div>
        <div style="padding: 9% 9% 0;">
            @foreach (['70%', '60%', '66%'] as $width)
                <div style="{{ $bar($width, '#d9dde3', '4px') }} margin-bottom: 6px;"></div>
            @endforeach
        </div>
        <div class="position-absolute rounded-circle" style="width: 14%; aspect-ratio: 1; right: 8%; bottom: 10%; background: var(--invoice-accent);"></div>
    @elseif ($value === 'minimal')
        <div style="padding: 11% 11% 0;">
            <div style="{{ $bar('34%', '#b8bec7', '4px') }} margin-bottom: 5px;"></div>
            <div style="{{ $bar('20%', '#d9dde3', '4px') }} margin-bottom: 16%;"></div>
            @foreach (['64%', '56%'] as $width)
                <div style="{{ $bar($width, '#eceef1', '3px') }} margin-bottom: 7px;"></div>
            @endforeach
        </div>
        <div class="position-absolute" style="right: 11%; bottom: 13%; width: 18%; height: 3px; background: var(--invoice-accent);"></div>
    @else
        <div style="padding: 10% 9% 0;">
            <div style="{{ $bar('42%', '#2b3138') }} margin-bottom: 5px;"></div>
            <div style="{{ $bar('26%', '#6b7480') }} margin-bottom: 7%;"></div>
            <div style="height: 2px; background: #2b3138; margin-bottom: 7%;"></div>
            @foreach (['72%', '62%'] as $width)
                <div style="{{ $bar($width, '#c4c9d0', '4px') }} margin-bottom: 6px;"></div>
            @endforeach
        </div>
        <div class="position-absolute" style="width: 12%; aspect-ratio: 1; right: 9%; bottom: 10%; background: #2b3138;"></div>
    @endif
</div>
