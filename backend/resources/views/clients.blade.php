@extends('layouts.app')

@section('title', ($client ? $client->full_name.' — ' : '').'Clients — Example Firm')

@section('content')
    @php
        $initials = fn (\App\Models\Client $person) => mb_substr($person->initials, 0, 2);

        $mobile = fn (\App\Models\Client $person) => $person->primaryContact(\App\Enums\Client\ContactKind::Mobile)?->value;
        $email = fn (\App\Models\Client $person) => $person->primaryContact(\App\Enums\Client\ContactKind::Email)?->value;

        $showMobile = fn (string $value) => \App\Services\Clients\MaskIdentifier::mobile($value);
        $showEmail = fn (string $value) => \App\Services\Clients\MaskIdentifier::email($value);
        $showId = fn (string $value) => \App\Services\Clients\MaskIdentifier::idNumber($value);
        $showRegistration = fn (string $value) => \App\Services\Clients\MaskIdentifier::registrationNumber($value);

        $details = function (\App\Models\Client $person) use ($canSeeContacts, $mobile, $email, $showMobile, $showEmail, $showId, $showRegistration) {
            return collect([
                $canSeeContacts && $mobile($person) ? $showMobile($mobile($person)) : null,
                $canSeeContacts && $email($person) ? $showEmail($email($person)) : null,
                $person->isEntity()
                    ? ($person->registration_number ? 'reg '.$showRegistration($person->registration_number) : null)
                    : ($person->id_number ? $showId($person->id_number) : null),
            ])->filter()->implode(' · ');
        };
    @endphp

    <div class="row g-0">
        <div class="col-12 col-lg-4 border-end pe-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h1 class="mb-0">Clients</h1>
                        <span class="text-meta">{{ $total }}</span>
                    </div>

                    <form method="GET" action="{{ route('clients') }}">
                        <x-ui.search-field name="q" :value="$query" placeholder="Name, ID number, phone…" :shortcut="false" wrapper="mb-3" class="w-100" />
                    </form>

                    @can('create', \App\Models\Client::class)
                        <x-ui.button variant="primary" icon="plus" class="w-100 mb-3"
                            x-data x-on:click="$dispatch('sheet-open', 'new-client')">New client intake</x-ui.button>
                    @endcan

                    <div class="list-group list-group-flush">
                        @forelse ($clients as $person)
                            <a href="{{ route('client.show', array_filter(['client' => $person, 'q' => $query])) }}"
                               @class(['list-group-item list-group-item-action d-flex align-items-start gap-2 px-3 py-2 text-decoration-none', 'active' => $client?->is($person)])
                               @if ($client?->is($person)) style="border-inline-start-color: var(--tblr-primary);" @endif>
                                <span @class(['avatar avatar-rounded flex-shrink-0 text-white', 'bg-dark' => ! $person->isEntity(), 'bg-info' => $person->isEntity()])>{{ $initials($person) }}</span>
                                <div class="flex-fill overflow-hidden">
                                    <div class="fw-bold text-truncate">{{ $person->full_name }}</div>
                                    <div class="text-meta text-truncate" title="{{ $details($person) }}">{{ $details($person) }}</div>
                                </div>
                                <div class="text-meta text-end flex-shrink-0">{{ $person->matters_count }}<br>{{ Str::plural('matter', $person->matters_count) }}</div>
                            </a>
                        @empty
                            <p class="text-secondary text-center py-4 mb-0">No clients to show.</p>
                        @endforelse
                    </div>

                    @if ($clients->hasPages())
                        <div class="d-flex align-items-center justify-content-between mt-3">
                            @if ($clients->onFirstPage())
                                <span></span>
                            @else
                                <a href="{{ $clients->previousPageUrl() }}" class="text-meta">← previous</a>
                            @endif

                            @if ($clients->hasMorePages())
                                <a href="{{ $clients->nextPageUrl() }}" class="text-meta">next →</a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8 ps-lg-4">
            @if ($client)
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <span @class(['avatar avatar-rounded text-white', 'bg-dark' => ! $client->isEntity(), 'bg-info' => $client->isEntity()]) style="--tblr-avatar-size: 3rem; font-size: 1rem;">{{ $initials($client) }}</span>
                        <div>
                            <h2 class="mb-1">{{ $client->full_name }}</h2>
                            <p class="text-meta mb-0">{{ $details($client) }}</p>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        @can('update', $client)
                            <x-ui.button variant="outline-secondary" size="sm"
                                x-data x-on:click="$dispatch('sheet-open', 'edit-client')">Edit</x-ui.button>
                        @endcan
                        @can('create', \App\Models\Matter::class)
                            <x-ui.button variant="primary" icon="plus" size="sm"
                                x-data x-on:click="$dispatch('sheet-open', 'new-client-matter')">New matter</x-ui.button>
                        @endcan
                    </div>
                </div>

                <div class="row row-cards mb-3">
                    <div class="col-4">
                        <x-ui.stat-card label="Open matters" :value="$openMattersCount" />
                    </div>
                    {{--
                    <div class="col-4">
                        <x-ui.stat-card label="Fees outstanding" value="R 4 200" />
                    </div>
                    --}}
                    <div class="col-4">
                        <x-ui.stat-card label="Client since" :value="$client->created_at->format('M Y')" />
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col">
                        <div class="card">
                            <div class="card-body">
                                <h3 class="text-meta text-uppercase mb-2">Matters</h3>
                                <div class="table-responsive">
                                    <table class="table table-vcenter">
                                        <tbody>
                                            @forelse ($matters as $matter)
                                                <tr>
                                                    <td class="text-meta text-nowrap"><a href="{{ route('matter.show', $matter) }}">{{ $matter->reference }}</a></td>
                                                    <td>{{ $matter->title }}</td>
                                                    <td>
                                                        @if ($matter->isClosed())
                                                            <x-ui.stage-badge tone="success">Closed</x-ui.stage-badge>
                                                        @else
                                                            <x-ui.stage-badge tone="info">Open</x-ui.stage-badge>
                                                        @endif
                                                    </td>
                                                    <td class="text-meta text-end">{{ $matter->instructed_at?->format('d M y') }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td class="text-center text-secondary py-3">No matters for this client.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    @if ($showConsent)
                        <div class="col-md-6">
                            <h3 class="text-meta text-uppercase mb-2">Popia consent</h3>
                            @if ($consent === null)
                                <p class="mb-0">No consent decision recorded.</p>
                            @elseif ($consent->isActive())
                                <p class="mb-0">Captured {{ $consent->granted_at->format('d M Y') }} · {{ Str::headline($consent->method->value) }}{{ $consent->mandate() ? ' · signed mandate on file' : '' }}</p>
                            @elseif ($consent->granted)
                                <p class="mb-0">Withdrawn {{ $consent->withdrawn_at->format('d M Y') }} · was captured {{ $consent->granted_at->format('d M Y') }}</p>
                            @else
                                <p class="mb-0">Consent declined · {{ Str::headline($consent->method->value) }}</p>
                            @endif
                        </div>
                    @endif
                    {{--
                    <div class="col-md-6">
                        <h3 class="text-meta text-uppercase mb-2">Conflict check</h3>
                        <p class="mb-0">Cleared 14 Aug 2026 by L. Radebe · no adverse party match</p>
                    </div>
                    --}}
                </div>
            @else
                <p class="text-secondary">Select a client to see their file.</p>
            @endif
        </div>
    </div>

    @if ($client)
        @can('update', $client)
            @php
                // Another form's failed input must not leak into this one.
                $editing = $errors->client_edit->any();
                $field = fn (string $name, mixed $current) => $editing ? old($name, $current) : $current;
            @endphp

            <x-ui.sheet name="edit-client" title="Edit client" :lede="$client->full_name" bag="client_edit"
                :action="route('client.update', $client)" method="PATCH">
                @if ($client->isEntity())
                    <x-ui.input label="Registered name" name="entity_name" id="edit_entity_name"
                        :value="$field('entity_name', $client->entity_name)" :error="$errors->client_edit->first('entity_name')" />

                    <x-ui.input label="Registration number" name="registration_number" id="edit_registration_number"
                        :value="$canSeeFullIdentifiers ? $field('registration_number', $client->registration_number) : $field('registration_number', '')"
                        :placeholder="$canSeeFullIdentifiers ? null : \App\Services\Clients\MaskIdentifier::registrationNumber((string) $client->registration_number)"
                        :hint="$canSeeFullIdentifiers ? 'e.g. 2011/123456/07' : 'Leave empty to keep the current number.'"
                        :error="$errors->client_edit->first('registration_number')" />
                @else
                    <x-ui.input label="First name" name="first_name" id="edit_first_name"
                        :value="$field('first_name', $client->first_name)" :error="$errors->client_edit->first('first_name')" />

                    <x-ui.input label="Last name" name="last_name" id="edit_last_name"
                        :value="$field('last_name', $client->last_name)" :error="$errors->client_edit->first('last_name')" />

                    <x-ui.input label="ID number" name="id_number" id="edit_id_number"
                        :value="$canSeeFullIdentifiers ? $field('id_number', $client->id_number) : $field('id_number', '')"
                        :placeholder="$canSeeFullIdentifiers ? null : \App\Services\Clients\MaskIdentifier::idNumber((string) $client->id_number)"
                        :hint="$canSeeFullIdentifiers ? null : 'Leave empty to keep the current number.'"
                        :error="$errors->client_edit->first('id_number')" />
                @endif

                <x-slot:footer>
                    <div class="d-flex align-items-center justify-content-between">
                        <x-ui.button variant="outline-secondary" x-on:click="close()">Cancel</x-ui.button>
                        <x-ui.button type="submit" variant="primary">Save changes</x-ui.button>
                    </div>
                </x-slot:footer>
            </x-ui.sheet>
        @endcan

        @can('create', \App\Models\Matter::class)
            <x-matter.intake name="new-client-matter" :client="$client" :matter-types="$matterTypes" :courts="$courts" :action="route('matter.store')" />
        @endcan
    @endif

    @can('create', \App\Models\Client::class)
        @php
            $intake = $errors->client;

            $intakeConfig = [
                'type' => old('type', 'individual'),
                'matterTypeId' => (string) old('matter_type_id', ''),
                'matterTypes' => $matterTypes->mapWithKeys(fn ($type) => [
                    $type->getKey() => ['litigation' => (bool) $type->is_litigation],
                ]),
            ];
        @endphp

        <x-ui.sheet name="new-client" title="New client intake" lede="Register a client, and open their matter if you are ready to." bag="client" wide
            :action="route('client.store')" :form-data="'clientIntake('.\Illuminate\Support\Js::from($intakeConfig).')'">
            {{-- Planned: shown beside the title once drafts can be saved
            <span class="badge">draft autosaved</span>
            --}}
            {{-- Planned: needs fees and receipts
            <p class="text-meta">Consultation fee R1 500 · receipt issued on save</p>
            --}}

            <h3 class="text-meta text-uppercase mb-2">Identity</h3>

            <div x-show="type === 'individual'">
                <div class="row">
                    <div class="col-md-6">
                        <x-ui.input label="Full names" name="first_name" :value="old('first_name')" :error="$intake->first('first_name')" />
                    </div>
                    <div class="col-md-6">
                        <x-ui.input label="Surname" name="last_name" :value="old('last_name')" :error="$intake->first('last_name')" />
                    </div>
                </div>
            </div>

            <div x-show="type === 'entity'" x-cloak>
                <x-ui.input label="Registered name" name="entity_name" :value="old('entity_name')" :error="$intake->first('entity_name')" />
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div x-show="type === 'individual'">
                        <x-ui.input label="ID number" name="id_number" :value="old('id_number')" :error="$intake->first('id_number')" />
                    </div>

                    <div x-show="type === 'entity'" x-cloak>
                        <x-ui.input label="Registration number" name="registration_number" :value="old('registration_number')"
                            hint="e.g. 2011/123456/07" :error="$intake->first('registration_number')" />
                    </div>
                </div>

                <div class="col-md-6">
                    <x-ui.select label="Client type" name="type" x-model="type" :error="$intake->first('type')">
                        <option value="individual">Individual</option>
                        <option value="entity">Entity</option>
                    </x-ui.select>
                </div>
            </div>

            <hr>

            <h3 class="text-meta text-uppercase mb-2">Contact</h3>

            <div class="row">
                <div class="col-md-6">
                    <x-ui.input label="Mobile" name="mobile" type="tel" :value="old('mobile')" hint="Used for SMS alerts" :error="$intake->first('mobile')" />
                </div>
                <div class="col-md-6">
                    <x-ui.input label="Email" name="email" type="email" :value="old('email')" :error="$intake->first('email')" />
                </div>
            </div>

            <x-ui.input label="Physical address" name="physical_address" :value="old('physical_address')" maxlength="500" :error="$intake->first('physical_address')" />

            @can('create', \App\Models\Matter::class)
                <hr>

                <h3 class="text-meta text-uppercase mb-2">Matter</h3>
                <p class="text-meta">Only needed when you choose Save &amp; open matter.</p>

                <x-ui.select label="Nature of instruction" name="matter_type_id" id="intake_matter_type_id" x-model="matterTypeId"
                    :error="$intake->first('matter_type_id')">
                    <option value="">Choose a matter type…</option>
                    @foreach ($matterTypes as $type)
                        <option value="{{ $type->getKey() }}" @selected((string) old('matter_type_id') === (string) $type->getKey())>{{ $type->name }}</option>
                    @endforeach
                </x-ui.select>

                <div x-show="litigation" x-cloak>
                    <x-ui.select label="Court" name="court_id" id="intake_court_id" before="Litigious matters are before a court"
                        x-bind:disabled="! litigation" :error="$intake->first('court_id')">
                        <option value="">Choose a court…</option>
                        @foreach ($courts as $court)
                            <option value="{{ $court->getKey() }}" @selected((string) old('court_id') === (string) $court->getKey())>{{ $court->label }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                {{-- Planned: shown once the matter type has sub-types, referral sources and a reference preview
                <x-ui.select label="Referred by" name="referred_by" />
                <p class="text-meta">reference will be NGA/2026/LAB/0135</p>
                --}}
                {{-- Planned: becomes the opening ledger entry
                <x-ui.input label="Summary of complaint" name="summary" before="becomes the opening ledger entry" />
                --}}
            @endcan

            @can('record_popia_consent')
                <hr>

                <div class="form-check">
                    <input class="form-check-input @if ($intake->has('popia_consent')) is-invalid @endif" type="checkbox"
                        id="intake_popia_consent" name="popia_consent" value="1" @checked(old('popia_consent'))>
                    <label class="form-check-label" for="intake_popia_consent">
                        Client confirms the information above is correct and consents to the firm processing their
                        personal information for the purposes of this matter (POPIA s.11). Consent is timestamped and
                        stored with the client record.
                    </label>
                    @if ($intake->has('popia_consent'))
                        <div class="invalid-feedback d-block">{{ $intake->first('popia_consent') }}</div>
                    @endif
                </div>
            @endcan

            <x-slot:footer>
                <div class="d-flex align-items-center justify-content-between">
                    <x-ui.button variant="outline-secondary" x-on:click="close()">Cancel</x-ui.button>

                    <div class="d-flex gap-2">
                        @can('create', \App\Models\Matter::class)
                            <x-ui.button type="submit" name="intent" value="client" variant="outline-secondary">Save client only</x-ui.button>
                            <x-ui.button type="submit" name="intent" value="matter" variant="primary">Save &amp; open matter →</x-ui.button>
                        @else
                            <x-ui.button type="submit" name="intent" value="client" variant="primary">Save client</x-ui.button>
                        @endcan
                    </div>
                </div>
            </x-slot:footer>
        </x-ui.sheet>
    @endcan
@endsection
