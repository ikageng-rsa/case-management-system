@props([
    'name',
    'matterTypes',
    'courts',
    'action',
    'client' => null,
    'selectedClient' => null,
    'attorneys' => collect(),
    'bag' => 'matter',
])

{{--
    Opening a matter takes a few steps — who it is for, what it is, then a last
    look — walked through client side and submitted as one ordinary form. Every
    field is validated again on the server, so a step can never be skipped by
    going around the page.

    Given a client (opening from their page) the step for choosing one is left
    out. Otherwise the client is found by searching, and a client who is not on
    file yet is registered from the clients page first.

    The parties step is for everyone else in the matter besides the client: the
    other side, their representatives, witnesses. It can be left empty and filled
    in later from the matter's Parties tab.
--}}
@php
    $errorBag = $errors->getBag($bag);
    $chosen = $client ?? $selectedClient;
    $matterStep = $client ? 1 : 2;
    $partiesStep = $matterStep + 1;

    $partyErrors = collect($errorBag->keys())->contains(fn ($key) => str_starts_with($key, 'parties'));

    $config = [
        'step' => match (true) {
            $errorBag->has('client_id') && ! $client => 1,
            $partyErrors => $partiesStep,
            $errorBag->any() => $matterStep,
            default => 1,
        },
        'fixedClient' => $client !== null,
        'client' => $chosen ? ['id' => $chosen->getKey(), 'name' => $chosen->full_name] : null,
        'types' => $matterTypes->mapWithKeys(fn ($type) => [
            $type->getKey() => ['name' => $type->name, 'litigation' => (bool) $type->is_litigation],
        ]),
        'typeId' => (string) old('matter_type_id', ''),
        'urls' => ['lookup' => route('client.lookup'), 'reference' => route('matter.reference-preview')],
        'parties' => collect(old('parties', []))->map(fn ($party) => [
            'name' => (string) ($party['name'] ?? ''),
            'role' => (string) ($party['role'] ?? \App\Enums\Matter\PartyRole::Respondent->value),
            'representative' => (string) ($party['representative'] ?? ''),
        ])->values(),
        'defaultRole' => \App\Enums\Matter\PartyRole::Respondent->value,
    ];
@endphp

<x-ui.sheet :name="$name" title="Open a new matter" :lede="$client ? 'For '.$client->full_name : 'Choose a client, then describe the matter.'" :bag="$bag" wide
    :action="$action" :form-data="'matterIntake('.\Illuminate\Support\Js::from($config).')'">
    <input type="hidden" name="client_id" x-bind:value="client?.id ?? ''">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <p class="text-meta mb-0">
            step <span x-text="step"></span> of <span x-text="steps.length"></span> ·
            <span x-text="current"></span>
        </p>

        <div class="d-flex gap-1">
            <template x-for="(label, index) in steps" :key="label">
                <span class="rounded-pill"
                      x-bind:class="index < step ? 'bg-primary' : 'bg-light'"
                      style="width: 20px; height: 3px;"></span>
            </template>
        </div>
    </div>

    @unless ($client)
        <div x-ref="step1" x-show="current === 'Client'">
            <div x-show="client === null">
                <div class="mb-3">
                    <label class="form-label form-label-mono" for="{{ $bag }}_client_search">Client</label>
                    <div class="form-hint">Search by name, ID number or phone</div>
                    <input type="search" class="form-control" id="{{ $bag }}_client_search" autocomplete="off"
                        x-model="query" x-on:input.debounce.300ms="search()"
                        x-bind:class="{ 'is-invalid': clientMissing || {{ $errorBag->has('client_id') ? 'true' : 'false' }} }">
                    <div class="invalid-feedback">Choose the client this matter is for.</div>
                </div>

                <div class="list-group list-group-flush mb-3">
                    <template x-for="result in results" :key="result.id">
                        <button type="button" class="list-group-item list-group-item-action d-flex align-items-start justify-content-between gap-2 px-3 py-2 text-start"
                            x-on:click="choose(result)">
                            <span>
                                <span class="d-block fw-bold" x-text="result.name"></span>
                                <span class="d-block text-meta" x-text="result.detail"></span>
                            </span>
                            <span class="text-meta text-end flex-shrink-0">
                                <span x-text="result.matters"></span>
                                <span x-text="result.matters === 1 ? 'matter' : 'matters'"></span>
                            </span>
                        </button>
                    </template>
                </div>

                <p class="text-secondary" x-show="searched && results.length === 0" x-cloak>No client matches that.</p>

                <p class="text-meta">
                    Not on file yet? <a href="{{ route('clients') }}">Register the client</a> first.
                </p>
            </div>

            <div x-show="client !== null" x-cloak>
                <div class="form-label form-label-mono">Client</div>
                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                    <span class="fw-bold" x-text="client?.name"></span>
                    <x-ui.button variant="ghost-secondary" size="sm" x-on:click="clear()">Change</x-ui.button>
                </div>
                <p class="text-meta" x-show="client?.matters > 0" x-cloak>
                    <span x-text="client?.matters"></span>
                    <span x-text="client?.matters === 1 ? 'existing matter' : 'existing matters'"></span> — check for duplicates.
                </p>
            </div>
        </div>
    @endunless

    <div x-ref="step{{ $matterStep }}" x-show="current === 'Matter'" {{ $client ? '' : 'x-cloak' }}>
        @if ($client)
            <div class="mb-3">
                <div class="form-label form-label-mono">Client</div>
                <div>{{ $client->full_name }}</div>
            </div>
        @endif

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

        <x-ui.input label="Matter title" name="title" id="{{ $bag }}_title" before="Appears on the file cover"
            x-ref="title" maxlength="255" :value="old('title')" :error="$errorBag->first('title')" />

        @if ($attorneys->isNotEmpty())
            <x-ui.select label="Responsible attorney" name="responsible_attorney_id" id="{{ $bag }}_attorney" before="Who owns the file"
                x-ref="attorney" :error="$errorBag->first('responsible_attorney_id')">
                <option value="">Assign later</option>
                @foreach ($attorneys as $attorney)
                    <option value="{{ $attorney->getKey() }}" @selected(old('responsible_attorney_id') === $attorney->getKey())>{{ $attorney->name }}</option>
                @endforeach
            </x-ui.select>
        @endif

        <x-ui.input label="Date of instruction" name="instructed_at" id="{{ $bag }}_instructed_at" type="date"
            before="The prescription clock starts here" x-ref="instructed" required
            :value="old('instructed_at', now()->toDateString())" :error="$errorBag->first('instructed_at')" />

        <div class="mb-3">
            <label class="form-label form-label-mono" for="{{ $bag }}_narration">Opening narration</label>
            <div class="form-hint">Optional first ledger entry</div>
            <textarea class="form-control @if ($errorBag->has('narration')) is-invalid @endif" id="{{ $bag }}_narration"
                name="narration" rows="3" maxlength="5000" x-ref="narration">{{ old('narration') }}</textarea>
            @if ($errorBag->has('narration'))
                <div class="invalid-feedback">{{ $errorBag->first('narration') }}</div>
            @endif
        </div>
    </div>

    <div x-ref="step{{ $partiesStep }}" x-show="current === 'Parties'" x-cloak>
        <p class="text-secondary">Anyone else in this matter besides {{ $client ? $client->full_name : 'the client' }}. You can leave this empty and add them later.</p>

        @if ($partyErrors)
            <div class="text-danger mb-3">{{ $errorBag->first(collect($errorBag->keys())->first(fn ($key) => str_starts_with($key, 'parties'))) }}</div>
        @endif

        <template x-for="(party, index) in parties" :key="index">
            <div class="border rounded p-3 mb-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-meta text-uppercase" x-text="'Party ' + (index + 1)"></span>
                    <button type="button" class="btn btn-link btn-sm p-0 text-danger" x-on:click="removeParty(index)">Remove</button>
                </div>

                <div class="mb-3">
                    <label class="form-label form-label-mono">Name</label>
                    <input type="text" class="form-control" maxlength="255" required
                        x-model="party.name" x-bind:name="`parties[${index}][name]`">
                </div>

                <div class="mb-3">
                    <label class="form-label form-label-mono">Role</label>
                    <select class="form-select" x-model="party.role" x-bind:name="`parties[${index}][role]`">
                        @foreach (\App\Enums\Matter\PartyRole::cases() as $role)
                            <option value="{{ $role->value }}">{{ $role->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-0">
                    <label class="form-label form-label-mono">Represented by</label>
                    <div class="form-hint">Optional</div>
                    <input type="text" class="form-control" maxlength="255"
                        x-model="party.representative" x-bind:name="`parties[${index}][representative]`">
                </div>
            </div>
        </template>

        <x-ui.button variant="outline-secondary" size="sm" icon="plus" x-on:click="addParty()">Add a party</x-ui.button>
    </div>

    <div x-ref="step{{ $partiesStep + 1 }}" x-show="current === 'Review'" x-cloak>
        <div class="p-3 mb-4 rounded" style="background-color: var(--cms-ink-900);">
            <p class="text-uppercase mb-1" style="font-family: var(--cms-font-mono); font-size: var(--cms-text-2xs); letter-spacing: var(--cms-tracking-label-wide); color: var(--cms-gold-500);">
                Reference
            </p>
            <p class="mb-0" style="font-family: var(--cms-font-mono); font-size: var(--cms-text-xl); color: #fff;"
                x-text="reference ?? 'issued when opened'"></p>
            <p class="mb-0 fst-italic" style="font-size: var(--cms-text-xs); color: var(--cms-on-ink-caption);">
                Provisional: the sequence is reserved when the matter is opened.
            </p>
        </div>

        <dl class="mb-0">
            <dt class="form-label form-label-mono">Client</dt>
            <dd x-text="client?.name"></dd>

            <dt class="form-label form-label-mono">Matter type</dt>
            <dd x-text="review.type"></dd>

            <dt class="form-label form-label-mono">Court</dt>
            <dd x-text="review.court"></dd>

            <dt class="form-label form-label-mono">Title</dt>
            <dd x-text="review.title"></dd>

            @if ($attorneys->isNotEmpty())
                <dt class="form-label form-label-mono">Responsible attorney</dt>
                <dd x-text="review.attorney"></dd>
            @endif

            <dt class="form-label form-label-mono">Instructed</dt>
            <dd x-text="review.instructed"></dd>

            <dt class="form-label form-label-mono">Opening narration</dt>
            <dd x-text="review.narration"></dd>

            <dt class="form-label form-label-mono">Parties</dt>
            <dd>
                <span x-show="! review.parties || review.parties.length === 0">None recorded yet</span>
                <template x-for="party in review.parties" :key="party.name + party.role">
                    <span class="d-block">
                        <span x-text="party.name"></span>
                        <span class="text-meta" x-text="'· ' + party.role + (party.representative ? ' · represented by ' + party.representative : '')"></span>
                    </span>
                </template>
            </dd>
        </dl>
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
                <x-ui.button variant="primary" x-show="current !== 'Review'" x-on:click="next()">Continue →</x-ui.button>
                <x-ui.button type="submit" variant="primary" x-show="current === 'Review'" x-cloak>Open matter</x-ui.button>
            </div>
        </div>
    </x-slot:footer>
</x-ui.sheet>
