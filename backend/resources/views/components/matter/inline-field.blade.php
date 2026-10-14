@props([
    'matter',
    'field',
    'label',
    'type' => 'text',
    'value' => null,
    'canEdit' => false,
    'placeholder' => null,
    'confirm' => null,
    'prompt' => null,
])

{{--
    A value of the matter that is its own control: the pencil turns it into a field in the
    same place, and saving sends only that field. The value and the field share one place, so
    whichever is not showing is not there; the toggle sits on a wrapper because a utility
    display class on the toggled element would beat the hiding.

    Given confirm, saving first asks in the confirm dialogue, and given prompt the dialogue
    also asks for the reason and sends it with the change.

    The slot is how the value is shown; an options slot makes the field a select.
--}}
<div x-data="{ editing: false }" x-on:click.outside="editing = false" x-on:keydown.escape.window="editing = false">
    <div x-show="! editing">
        @if ($canEdit)
            <button type="button" class="border-0 bg-transparent p-0 text-start" style="font: inherit; color: inherit;"
                x-on:click="editing = true; $nextTick(() => $refs.field.focus())" title="Change the {{ $label }}">
                {{ $slot }}
                <x-ui.icon name="pencil" class="text-secondary ms-1" />
            </button>
        @else
            {{ $slot }}
        @endif
    </div>

    @if ($canEdit)
        <div x-show="editing" x-cloak>
            <form method="POST" action="{{ route('matter.update', $matter) }}" class="d-flex align-items-center gap-1"
                @if ($confirm)
                    data-confirm="{{ $confirm }}" data-confirm-title="Change the {{ $label }}?" data-confirm-action="Change"
                    data-confirm-tone="primary" data-confirm-icon="calendar"
                @endif
                @if ($prompt)
                    data-confirm-prompt-label="{{ $prompt }}" data-confirm-prompt-field="reason"
                @endif>
                @csrf
                @method('PATCH')

                @if (isset($options))
                    <select x-ref="field" name="{{ $field }}" class="form-select form-select-sm" aria-label="{{ ucfirst($label) }}">{{ $options }}</select>
                @else
                    <input x-ref="field" type="{{ $type }}" name="{{ $field }}" value="{{ $value }}" placeholder="{{ $placeholder }}"
                        class="form-control form-control-sm" aria-label="{{ ucfirst($label) }}" @if ($field === 'title') maxlength="255" @endif>
                @endif

                @if ($prompt)
                    <input type="hidden" name="reason" value="">
                @endif

                <x-ui.button type="submit" size="sm" variant="primary" icon="check" title="Save" aria-label="Save the {{ $label }}" />
                <x-ui.button size="sm" variant="ghost-secondary" icon="x" title="Cancel" aria-label="Cancel" x-on:click="editing = false" />
            </form>
        </div>
    @endif
</div>
