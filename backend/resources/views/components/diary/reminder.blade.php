@props([
    'minutes' => null,
    'id' => 'remind',
    'label' => 'Remind',
    'hint' => null,
    'error' => null,
    'useOld' => false,
])

{{--
    How long before an entry falls due the person is reminded: a few usual steps
    to choose from, or "another time" with a number and a unit. Sends three
    fields, remind, remind_value and remind_unit, which ReminderLead reads.
    useOld puts back what was typed after a form came back with errors.
--}}
@php
    $start = \App\Services\Diary\ReminderLead::asChoice($minutes);

    $choice = (string) ($useOld ? old('remind', $start['choice']) : $start['choice']);
    $value = $useOld ? old('remind_value', $start['value']) : $start['value'];
    $unit = $useOld ? old('remind_unit', $start['unit']) : $start['unit'];
@endphp

<div class="mb-3" x-data="{ choice: @js($choice) }">
    <label class="form-label form-label-mono" for="{{ $id }}">{{ $label }}</label>

    <select id="{{ $id }}" name="remind" class="form-select @if ($error) is-invalid @endif" x-model="choice">
        <option value="none">No reminder</option>
        @foreach (\App\Services\Diary\ReminderLead::PRESETS as $presetMinutes => $presetLabel)
            <option value="{{ $presetMinutes }}">{{ $presetLabel }}</option>
        @endforeach
        <option value="custom">Another time…</option>
    </select>

    <div class="row g-2 mt-1" x-show="choice === 'custom'" x-cloak>
        <div class="col-6">
            <input type="number" min="1" name="remind_value" class="form-control" placeholder="How many" value="{{ $value }}"
                aria-label="How long before">
        </div>
        <div class="col-6">
            <select name="remind_unit" class="form-select" aria-label="Unit">
                @foreach (\App\Services\Diary\ReminderLead::units() as $option)
                    <option value="{{ $option }}" @selected($unit === $option)>{{ $option }} before</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($error)
        <div class="invalid-feedback d-block">{{ $error }}</div>
    @elseif ($hint)
        <div class="form-hint">{{ $hint }}</div>
    @endif
</div>
