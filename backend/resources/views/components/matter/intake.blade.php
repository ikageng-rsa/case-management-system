@props([
    'name',
    'client',
    'matterTypes',
    'courts',
    'action',
    'bag' => 'matter',
])

{{--
    Opening a matter takes three steps — what kind of matter, its details, then
    a last look — walked through client side and submitted as one ordinary form.
    Every field is validated again on the server, so a step can never be skipped
    by going around the page.

    The client is fixed here. Where the intake is opened without one, a step for
    choosing a client goes in front of these.
--}}
@php
    $errorBag = $errors->getBag($bag);

    $config = [
        'step' => match (true) {
            $errorBag->hasAny(['matter_type_id', 'court_id', 'client_id']) => 1,
            $errorBag->any() => 2,
            default => 1,
        },
        'types' => $matterTypes->mapWithKeys(fn ($type) => [
            $type->getKey() => ['name' => $type->name, 'litigation' => (bool) $type->is_litigation],
        ]),
        'typeId' => (string) old('matter_type_id', ''),
    ];
@endphp

<x-ui.sheet :name="$name" title="Open a new matter" :lede="'For '.$client->full_name" :bag="$bag" wide
    :action="$action" :form-data="'matterIntake('.\Illuminate\Support\Js::from($config).')'">
    <input type="hidden" name="client_id" value="{{ $client->getKey() }}">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <p class="text-meta mb-0">
            step <span x-text="step"></span> of <span x-text="steps.length"></span> ·
            <span x-text="steps[step - 1]"></span>
        </p>

        <div class="d-flex gap-1">
            <template x-for="(label, index) in steps" :key="label">
                <span class="rounded-pill"
                      x-bind:class="index < step ? 'bg-primary' : 'bg-light'"
                      style="width: 20px; height: 3px;"></span>
            </template>
        </div>
    </div>

    <div x-ref="step1" x-show="step === 1">
        <div class="mb-3">
            <div class="form-label form-label-mono">Client</div>
            <div>{{ $client->full_name }}</div>
        </div>

        <x-ui.select label="Matter type" name="matter_type_id" id="{{ $bag }}_matter_type_id" before="Drives the reference code"
            x-model="typeId" x-ref="type" required :error="$errorBag->first('matter_type_id')">
            <option value="">Choose a type…</option>
            @foreach ($matterTypes as $type)
                <option value="{{ $type->getKey() }}" @selected((string) old('matter_type_id') === (string) $type->getKey())>{{ $type->name }}</option>
            @endforeach
        </x-ui.select>

        <div x-show="litigation" x-cloak>
            <x-ui.select label="Court" name="court_id" id="{{ $bag }}_court_id" before="Litigious matters are before a court"
                x-ref="court" x-bind:required="litigation" x-bind:disabled="! litigation" :error="$errorBag->first('court_id')">
                <option value="">Choose a court…</option>
                @foreach ($courts as $court)
                    <option value="{{ $court->getKey() }}" @selected((string) old('court_id') === (string) $court->getKey())>{{ $court->label }}</option>
                @endforeach
            </x-ui.select>
        </div>
    </div>

    <div x-ref="step2" x-show="step === 2" x-cloak>
        <x-ui.input label="Matter title" name="title" id="{{ $bag }}_title" before="Appears on the file cover"
            x-ref="title" maxlength="255" :value="old('title')" :error="$errorBag->first('title')" />

        <x-ui.input label="Date of instruction" name="instructed_at" id="{{ $bag }}_instructed_at" type="date"
            before="The prescription clock starts here" x-ref="instructed" required
            :value="old('instructed_at', now()->toDateString())" :error="$errorBag->first('instructed_at')" />
    </div>

    <div x-ref="step3" x-show="step === 3" x-cloak>
        <dl class="mb-4">
            <dt class="form-label form-label-mono">Client</dt>
            <dd>{{ $client->full_name }}</dd>

            <dt class="form-label form-label-mono">Matter type</dt>
            <dd x-text="review.type"></dd>

            <dt class="form-label form-label-mono">Court</dt>
            <dd x-text="review.court"></dd>

            <dt class="form-label form-label-mono">Title</dt>
            <dd x-text="review.title"></dd>

            <dt class="form-label form-label-mono">Instructed</dt>
            <dd x-text="review.instructed"></dd>
        </dl>

        <p class="text-meta fst-italic">The file reference is issued when the matter is opened.</p>
    </div>

    <x-slot:footer>
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <x-ui.button variant="outline-secondary" x-show="step > 1" x-on:click="back()">
                    <x-ui.icon name="arrow-left" class="me-1" /> Back
                </x-ui.button>
                <x-ui.button variant="outline-secondary" x-show="step === 1" x-on:click="close()">Cancel</x-ui.button>
            </div>

            <div>
                <x-ui.button variant="primary" x-show="step < 3" x-on:click="next()">Continue →</x-ui.button>
                <x-ui.button type="submit" variant="primary" x-show="step === 3" x-cloak>Open matter</x-ui.button>
            </div>
        </div>
    </x-slot:footer>
</x-ui.sheet>
