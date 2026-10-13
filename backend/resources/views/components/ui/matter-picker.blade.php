@props([
    'name',
    'label' => null,
    'scope' => 'visible',
    'selected' => null,
    'error' => null,
    'hint' => null,
])

{{--
    Pick one matter out of any number. Type a few words — a year, a type, part of
    a reference, a client — and the five best matches are offered; the one chosen
    travels in an ordinary hidden field, so the form still submits as usual.

    scope="fileable" offers only the open matters the user may put work on.
--}}
@php
    $config = [
        'url' => route('matter.search'),
        'scope' => $scope,
        'selected' => $selected ? [
            'id' => $selected->getKey(),
            'reference' => $selected->reference,
            'title' => $selected->title,
            'client' => $selected->client->full_name,
            'type' => $selected->matterType->name,
            'year' => $selected->opened_year,
        ] : null,
    ];

    $id = $attributes->get('id', $name.'_search');
@endphp

<div class="mb-3" x-data="matterPicker({{ \Illuminate\Support\Js::from($config) }})" x-on:click.outside="open = false">
    @if ($label)
        <label class="form-label form-label-mono" for="{{ $id }}">{{ $label }}</label>
    @endif

    <input type="hidden" name="{{ $name }}" x-bind:value="matter?.id ?? ''">

    <div x-show="matter" x-cloak class="form-control d-flex align-items-center justify-content-between gap-2 @if ($error) is-invalid @endif">
        <span class="overflow-hidden">
            <span class="d-block text-truncate"><span class="text-meta" x-text="matter?.reference"></span> <span x-text="matter?.title"></span></span>
            <span class="d-block text-meta text-truncate" x-text="matter ? matter.client + ' · ' + matter.type + ' · ' + matter.year : ''"></span>
        </span>
        <button type="button" class="btn btn-link btn-sm p-0 flex-shrink-0" x-on:click="clear()">Change</button>
    </div>

    <div x-show="! matter">
        <input type="search" id="{{ $id }}" x-ref="search" class="form-control @if ($error) is-invalid @endif" autocomplete="off"
            placeholder="Type a year, a type, a reference or a client…"
            x-model="query"
            x-on:focus="focus()"
            x-on:input.debounce.250ms="open = true; search()"
            x-on:keydown.arrow-down.prevent="move(1)"
            x-on:keydown.arrow-up.prevent="move(-1)"
            x-on:keydown.enter.prevent="choose()"
            x-on:keydown.escape.stop="open = false">

        <div class="list-group list-group-flush mt-2" x-show="open && results.length" x-cloak role="listbox">
            <template x-for="(result, index) in results" :key="result.id">
                <button type="button" role="option" class="list-group-item list-group-item-action px-3 py-2 text-start"
                    x-bind:class="{ active: index === active }"
                    x-on:click="pick(result)" x-on:mouseenter="active = index">
                    <span class="d-block"><span class="text-meta" x-text="result.reference"></span> <span x-text="result.title"></span></span>
                    <span class="d-block text-meta" x-text="result.client + ' · ' + result.type + ' · ' + result.year"></span>
                </button>
            </template>
        </div>

        <p class="text-secondary mt-2 mb-0" x-show="open && searched && results.length === 0" x-cloak>No matter fits that.</p>
    </div>

    @if ($error)
        <div class="invalid-feedback d-block">{{ $error }}</div>
    @elseif ($hint)
        <div class="form-hint">{{ $hint }}</div>
    @endif
</div>
