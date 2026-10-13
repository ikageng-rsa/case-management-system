@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'before' => null,
    'hint' => null,
    'error' => null,
    'wrapper' => null,
])

@php($selectId = $id ?? $name)

<div @class(array_filter(['mb-3', $wrapper]))>
    @if ($label)
        <label class="form-label form-label-mono" for="{{ $selectId }}">
            {{ $label }}
        </label>
    @endif

    @if ($before)
        <div class="form-hint">{{ $before }}</div>
    @endif

    <select
        @if ($name) name="{{ $name }}" @endif
        @if ($selectId) id="{{ $selectId }}" @endif
        {{ $attributes->class(['form-select', 'is-invalid' => filled($error)]) }}
    >
        {{ $slot }}
    </select>

    @if ($error)
        <div class="invalid-feedback">{{ $error }}</div>
    @elseif ($hint)
        <div class="form-hint">{{ $hint }}</div>
    @endif
</div>
