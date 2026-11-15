@props([
    'theme',
])

{{--
    A drawing of how a theme lays a bill out, not a preview of a real one. The
    strip across the top stands for the firm's letterhead, which keeps its own
    colours in every theme; what the theme changes is drawn under it: the
    reference box, the heading, the lines and the total. The accent is read from
    --invoice-accent on a parent, so a colour picker can change every drawing.
--}}
@php
    $value = $theme instanceof \App\Enums\Billing\InvoiceTheme ? $theme->value : $theme;
    $bar = fn (string $width, string $colour, string $height = '4px') => "height: {$height}; width: {$width}; background: {$colour}; border-radius: 2px;";
    $ink = $value === 'classic' ? '#2b3138' : ($value === 'modern' ? 'var(--invoice-accent)' : '#c4c9d0');
@endphp

<div {{ $attributes->class(['position-relative overflow-hidden bg-white border rounded-3']) }} style="aspect-ratio: 16 / 10;" aria-hidden="true">
    {{-- The letterhead. --}}
    <div class="d-flex align-items-center gap-1" style="padding: 6% 7% 3%; border-bottom: 1px solid #e4e7eb;">
        <div style="width: 10%; aspect-ratio: 1; background: #9aa5b1; border-radius: 2px;"></div>
        <div style="flex: 1;">
            <div style="{{ $bar('46%', '#9aa5b1') }} margin-bottom: 3px;"></div>
            <div style="{{ $bar('28%', '#d9dde3', '3px') }}"></div>
        </div>
    </div>

    <div style="padding: 4% 7% 0;">
        {{-- Attention to on the left, the reference box on the right. --}}
        <div class="d-flex justify-content-between align-items-start" style="margin-bottom: 4%;">
            <div style="width: 38%;">
                <div style="{{ $bar('80%', $value === 'modern' ? 'var(--invoice-accent)' : '#6b7480') }} margin-bottom: 3px;"></div>
                <div style="{{ $bar('60%', '#d9dde3', '3px') }}"></div>
            </div>
            @if ($value === 'minimal')
                <div style="width: 30%;">
                    <div style="height: 1px; background: #e4e7eb; margin-bottom: 5px;"></div>
                    <div style="height: 1px; background: #e4e7eb;"></div>
                </div>
            @else
                <div style="width: 30%; height: 14px; border: 1px solid {{ $ink }}; background: linear-gradient(to right, {{ $value === 'modern' ? 'var(--invoice-accent)' : 'transparent' }} 45%, transparent 45%);"></div>
            @endif
        </div>

        {{-- The heading. --}}
        @if ($value === 'modern')
            <div style="height: 8px; background: var(--invoice-accent); border-radius: 2px; margin-bottom: 4%;"></div>
        @elseif ($value === 'classic')
            <div style="border-top: 2px solid #2b3138; border-bottom: 2px solid #2b3138; padding: 3px 0; margin-bottom: 4%;">
                <div class="mx-auto" style="{{ $bar('55%', '#2b3138', '3px') }}"></div>
            </div>
        @else
            <div class="mx-auto" style="{{ $bar('45%', '#d9dde3', '3px') }} margin-bottom: 5%;"></div>
        @endif

        {{-- The lines. --}}
        @foreach (['72%', '62%'] as $width)
            <div style="{{ $bar($width, $value === 'minimal' ? '#eceef1' : '#d9dde3', '3px') }} margin-bottom: 5px;"></div>
        @endforeach
    </div>

    {{-- The total. --}}
    @if ($value === 'minimal')
        <div class="position-absolute" style="right: 7%; bottom: 9%; width: 18%; height: 2px; background: var(--invoice-accent);"></div>
    @else
        <div class="position-absolute" style="right: 7%; bottom: 8%; width: 20%; height: 6px; background: {{ $ink }}; border-radius: 2px;"></div>
    @endif
</div>
