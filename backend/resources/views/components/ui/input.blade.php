@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'type' => 'text',
    'tone' => null,
    'hint' => null,
    'error' => null,
    'wrapper' => null,
])

@php
    $inputId = $id ?? $name;
    $isDark = $tone === 'dark';
@endphp

<div @class(array_filter(['mb-3', $wrapper]))>
    @if ($label)
        <label @class(['form-label', 'form-label-mono', 'form-label-mono-dark' => $isDark]) for="{{ $inputId }}">
            {{ $label }}
        </label>
    @endif

    <input
        type="{{ $type }}"
        @if ($name) name="{{ $name }}" @endif
        @if ($inputId) id="{{ $inputId }}" @endif
        {{ $attributes->class([
            'form-control',
            'form-control-dark' => $isDark,
            'is-invalid' => filled($error),
        ]) }}
    >

    @if ($error)
        <div class="invalid-feedback">{{ $error }}</div>
    @elseif ($hint)
        <div class="form-hint">{{ $hint }}</div>
    @endif
</div>
