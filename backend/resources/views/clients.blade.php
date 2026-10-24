@extends('layouts.app')

@section('title', ($client ? $client->full_name.' — ' : '').'Clients — Example Firm')

@section('content')
    @php
        $initials = fn (\App\Models\Client $person) => mb_substr($person->initials, 0, 2);

        $mobile = fn (\App\Models\Client $person) => $person->primaryContact(\App\Enums\Client\ContactKind::Mobile)?->value;
        $email = fn (\App\Models\Client $person) => $person->primaryContact(\App\Enums\Client\ContactKind::Email)?->value;

        $showMobile = fn (string $value) => \App\Services\Clients\MaskIdentifier::mobile($value);
        $showEmail = fn (string $value) => \App\Services\Clients\MaskIdentifier::email($value);
        // A passport or other document is named, so its number is not taken for a South African ID.
        $showId = fn (\App\Models\Client $person) => ltrim($person->id_type?->prefix().' '.\App\Services\Clients\MaskIdentifier::idNumber($person->id_number));
        $showRegistration = fn (string $value) => \App\Services\Clients\MaskIdentifier::registrationNumber($value);

        $details = function (\App\Models\Client $person) use ($canSeeContacts, $mobile, $email, $showMobile, $showEmail, $showId, $showRegistration) {
            return collect([
                $canSeeContacts && $mobile($person) ? $showMobile($mobile($person)) : null,
                $canSeeContacts && $email($person) ? $showEmail($email($person)) : null,
                $person->isEntity()
                    ? ($person->registration_number ? 'reg '.$showRegistration($person->registration_number) : null)
                    : ($person->id_number ? $showId($person) : null),
            ])->filter()->implode(' · ');
        };
    @endphp

    <div class="row g-0">
        <div class="col-12 col-lg-4 border-end pe-lg-4">
            {{-- The heading, search and intake button stay put while the clients scroll beneath them. --}}
            <div class="card" style="max-height: calc(100vh - 9rem);">
                <div class="card-body d-flex flex-column overflow-hidden">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-shrink-0">
                        <h1 class="mb-0">Clients</h1>
                        <span class="text-meta">{{ $total }}</span>
                    </div>

                    <form method="GET" action="{{ route('clients') }}" class="flex-shrink-0">
                        <x-ui.search-field name="q" :value="$query" placeholder="Name, ID number, phone…" :shortcut="false" wrapper="mb-3" class="w-100" />
                    </form>

                    @can('create', \App\Models\Client::class)
                        <x-ui.button variant="primary" icon="plus" class="w-100 mb-3 flex-shrink-0"
                            x-data x-on:click="$dispatch('sheet-open', 'new-client')">New client intake</x-ui.button>
                    @endcan

                    <div class="list-group list-group-flush overflow-y-auto flex-grow-1">
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
                        <div class="d-flex align-items-center justify-content-between mt-3 flex-shrink-0">
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
                            @php
                                $headerItems = collect([
                                    ...collect([\App\Enums\Client\ContactKind::Mobile, \App\Enums\Client\ContactKind::Email])
                                        ->map(fn ($kind) => $canSeeContacts ? $client->primaryContact($kind) : null)
                                        ->filter()
                                        ->map(fn ($contact) => [
                                            'masked' => $contact->kind === \App\Enums\Client\ContactKind::Mobile ? $showMobile($contact->value) : $showEmail($contact->value),
                                            'target' => 'contact:'.$contact->getKey(),
                                        ]),
                                    $client->isEntity()
                                        ? ($client->registration_number ? ['masked' => 'reg '.$showRegistration($client->registration_number), 'target' => 'identifier'] : null)
                                        : ($client->id_number ? ['masked' => $showId($client), 'target' => 'identifier'] : null),
                                    $client->date_of_birth ? ['label' => 'born', 'masked' => \App\Services\Clients\MaskIdentifier::dateOfBirth(), 'target' => 'date_of_birth'] : null,
                                ])->filter()->values();
                            @endphp
                            <p class="text-meta mb-0 d-flex flex-wrap align-items-center gap-1">
                                @foreach ($headerItems as $item)
                                    @isset ($item['label'])
                                        <span>{{ $item['label'] }}</span>
                                    @endisset
                                    <x-client.reveal :masked="$item['masked']" :target="$item['target']" :client="$client" :can-reveal="$canReveal" />
                                    @unless ($loop->last)
                                        <span>·</span>
                                    @endunless
                                @endforeach
                            </p>
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

                @php
                    $tabLinks = ['overview' => 'Overview'];

                    if ($canSeeContacts) {
                        $tabLinks += ['contacts' => 'Contacts', 'persons' => 'Contact persons'];
                    }
                @endphp

                <ul class="nav nav-underline mb-3">
                    @foreach ($tabLinks as $key => $label)
                        <li class="nav-item">
                            <a href="{{ route('client.show', array_filter(['client' => $client, 'tab' => $key === 'overview' ? null : $key, 'q' => $query])) }}"
                                @class(['nav-link', 'active' => $tab === $key])>{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>

                @if ($tab === 'contacts')
                    @include('clients.contacts')
                @elseif ($tab === 'persons')
                    @include('clients.persons')
                @else
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

                    @php
                        $outstanding = count($client->outstandingFicaDocuments());
                    @endphp

                    <div class="row mb-3">
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <h3 class="text-meta text-uppercase mb-0">FICA documents</h3>
                                            @if ($outstanding === 0)
                                                <x-ui.stage-badge tone="success">Complete</x-ui.stage-badge>
                                            @else
                                                <x-ui.stage-badge tone="danger">{{ $outstanding }} outstanding</x-ui.stage-badge>
                                            @endif
                                        </div>

                                        @can('update', $client)
                                            <x-ui.button variant="outline-secondary" size="sm" icon="upload"
                                                x-data x-on:click="$dispatch('sheet-open', 'upload-fica')">Upload</x-ui.button>
                                        @endcan
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-vcenter">
                                            <tbody>
                                                @foreach (\App\Enums\Client\FicaDocument::forType($client->type) as $document)
                                                    @php
                                                        $files = $client->ficaDocuments($document);
                                                    @endphp
                                                    <tr>
                                                        <td>
                                                            <div>{{ $document->label() }}</div>
                                                            <div class="text-meta text-secondary">{{ $document->hint() }}</div>
                                                        </td>
                                                        <td>
                                                            @forelse ($files as $file)
                                                                <div class="d-flex align-items-center justify-content-end gap-2 text-nowrap">
                                                                    {{-- A copy of an ID shows the number in full, so only those cleared to see it may open one. --}}
                                                                    @if ($canDownloadFica)
                                                                        <a href="{{ route('client.fica.download', [$client, $file->getKey()]) }}">{{ $file->file_name }}</a>
                                                                    @else
                                                                        <span>{{ $file->file_name }}</span>
                                                                    @endif
                                                                    <span class="text-meta">{{ $file->created_at->format('d M Y') }}</span>

                                                                    @if ($canDeleteFica)
                                                                        <form method="POST" action="{{ route('client.fica.destroy', [$client, $file->getKey()]) }}" class="d-inline"
                                                                            data-confirm="Delete {{ $file->file_name }}? This cannot be undone."
                                                                            data-confirm-title="Delete FICA document" data-confirm-action="Delete" data-confirm-tone="danger" data-confirm-icon="trash">
                                                                            @csrf
                                                                            @method('DELETE')
                                                                            <x-ui.button type="submit" variant="ghost-danger" size="sm">Delete</x-ui.button>
                                                                        </form>
                                                                    @endif
                                                                </div>
                                                            @empty
                                                                <div class="text-end"><x-ui.stage-badge>Outstanding</x-ui.stage-badge></div>
                                                            @endforelse
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        @if ($showConsent)
                            @php
                                $active = (bool) $consent?->isActive();
                                $withdrawn = $consent && $consent->granted && ! $active;
                                $declined = $consent && ! $consent->granted;
                                $mandate = fn ($record) => $record->mandate()
                                    ? '<a href="'.e(route('client.consent.mandate', [$client, $record])).'">signed mandate</a>'
                                    : null;
                            @endphp

                            <div class="col-md-6" x-data>
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <h3 class="text-meta text-uppercase mb-0">Popia consent</h3>

                                    {{-- On is only reached by recording consent, and off only by withdrawing it, so the switch
                                         never flips by itself; it asks. A withdrawn consent stays withdrawn. --}}
                                    <label class="form-check form-switch mb-0"
                                        @if ($withdrawn) title="Withdrawn consent cannot be switched back on. Record new consent instead." @endif>
                                        <input class="form-check-input" type="checkbox" role="switch" aria-label="Popia consent"
                                            @checked($active)
                                            @disabled($withdrawn || ($active ? ! $canWithdrawConsent : ! $canRecordConsent))
                                            @if ($active)
                                                x-on:click.prevent="$refs.withdraw.requestSubmit()"
                                            @else
                                                x-on:click.prevent="$dispatch('sheet-open', 'record-consent')"
                                            @endif>
                                    </label>
                                </div>

                                @if ($consent === null)
                                    <p class="mb-0">No consent decision recorded.</p>
                                @elseif ($active)
                                    <p class="mb-0">Captured {{ $consent->granted_at->format('d M Y') }} · {{ Str::headline($consent->method->value) }}@if ($link = $mandate($consent)) · {!! $link !!} on file @endif</p>
                                @elseif ($withdrawn)
                                    <p class="mb-0">Withdrawn {{ $consent->withdrawn_at->format('d M Y') }} · was captured {{ $consent->granted_at->format('d M Y') }}</p>
                                @else
                                    <p class="mb-0">Consent declined · {{ Str::headline($consent->method->value) }}</p>
                                @endif

                                @if ($canRecordConsent && ($withdrawn || $declined))
                                    <x-ui.button variant="outline-secondary" size="sm" class="mt-2"
                                        x-on:click="$dispatch('sheet-open', 'record-consent')">Record new consent</x-ui.button>
                                @endif

                                @if ($active && $canWithdrawConsent)
                                    <form x-ref="withdraw" method="POST" action="{{ route('client.consent.withdraw', $client) }}"
                                        data-confirm="Withdraw this client's consent? It cannot be switched back on; if they consent again, that is recorded as a new consent."
                                        data-confirm-title="Withdraw consent" data-confirm-action="Withdraw" data-confirm-tone="danger" data-confirm-icon="shield-x">
                                        @csrf
                                    </form>
                                @endif

                                @if ($consents->count() > 1)
                                    <h4 class="text-meta text-uppercase mt-3 mb-1">History</h4>
                                    @foreach ($consents as $record)
                                        <p class="text-meta mb-1">
                                            @if ($record->isActive())
                                                Granted {{ $record->granted_at->format('d M Y') }} · in force
                                            @elseif ($record->granted)
                                                Granted {{ $record->granted_at->format('d M Y') }} · withdrawn {{ $record->withdrawn_at->format('d M Y') }}
                                            @else
                                                Declined {{ $record->created_at->format('d M Y') }}
                                            @endif
                                            · {{ Str::headline($record->method->value) }}@if ($link = $mandate($record)) · {!! $link !!} @endif
                                        </p>
                                    @endforeach
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
                @endif
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

                    {{-- An identifier is never printed into a form: reading one in full is the eye's job, which
                         is checked and logged. Left empty here, the number on file is kept. --}}
                    <x-ui.input label="Registration number" name="registration_number" id="edit_registration_number"
                        :value="$field('registration_number', '')"
                        :placeholder="\App\Services\Clients\MaskIdentifier::registrationNumber((string) $client->registration_number)"
                        hint="Leave empty to keep the current number. Use the eye on the client to read it."
                        :error="$errors->client_edit->first('registration_number')" />
                @else
                    <x-ui.input label="First name" name="first_name" id="edit_first_name"
                        :value="$field('first_name', $client->first_name)" :error="$errors->client_edit->first('first_name')" />

                    <x-ui.input label="Last name" name="last_name" id="edit_last_name"
                        :value="$field('last_name', $client->last_name)" :error="$errors->client_edit->first('last_name')" />

                    <x-ui.select label="Identity document" name="id_type" id="edit_id_type" :error="$errors->client_edit->first('id_type')">
                        @foreach (\App\Enums\Client\IdentityType::cases() as $identity)
                            <option value="{{ $identity->value }}" @selected($field('id_type', ($client->id_type ?? \App\Enums\Client\IdentityType::SouthAfricanId)->value) === $identity->value)>{{ $identity->label() }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.input label="ID or passport number" name="id_number" id="edit_id_number"
                        :value="$field('id_number', '')"
                        :placeholder="$client->id_number ? \App\Services\Clients\MaskIdentifier::idNumber($client->id_number) : 'Not on file'"
                        :hint="$client->id_number ? 'Leave empty to keep the current number. Use the eye on the client to read it.' : 'Optional.'"
                        :error="$errors->client_edit->first('id_number')" />

                    {{-- Like the number, the date on file is never printed into the form. --}}
                    <x-ui.input label="Date of birth" name="date_of_birth" id="edit_date_of_birth" type="date"
                        :value="$field('date_of_birth', '')" max="{{ now()->subDay()->toDateString() }}"
                        :hint="$client->date_of_birth ? 'Leave empty to keep the date on file. Use the eye on the client to read it.' : 'Optional. Read from a South African ID number when left empty.'"
                        :error="$errors->client_edit->first('date_of_birth')" />
                @endif

                <x-slot:footer>
                    <div class="d-flex align-items-center justify-content-between">
                        <x-ui.button variant="outline-secondary" x-on:click="close()">Cancel</x-ui.button>
                        <x-ui.button type="submit" variant="primary">Save changes</x-ui.button>
                    </div>
                </x-slot:footer>
            </x-ui.sheet>
        @endcan

        @can('update', $client)
            <x-ui.sheet name="upload-fica" title="Upload FICA document" :lede="$client->full_name" bag="fica"
                :action="route('client.fica.store', $client)" enctype="multipart/form-data">
                <x-ui.select label="Document" name="document" id="fica_document" :error="$errors->fica->first('document')">
                    @foreach (\App\Enums\Client\FicaDocument::forType($client->type) as $document)
                        <option value="{{ $document->value }}" @selected(old('document') === $document->value)>{{ $document->label() }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.input label="File" name="file" id="fica_file" type="file"
                    accept="{{ collect(array_keys(config('documents.allowed')))->map(fn ($extension) => '.'.$extension)->implode(',') }}"
                    hint="PDF or a scan, up to 50 MB." :error="$errors->fica->first('file')" />

                <x-slot:footer>
                    <div class="d-flex align-items-center justify-content-between">
                        <x-ui.button variant="outline-secondary" x-on:click="close()">Cancel</x-ui.button>
                        <x-ui.button type="submit" variant="primary">Upload</x-ui.button>
                    </div>
                </x-slot:footer>
            </x-ui.sheet>
        @endcan

        @if ($showConsent && $canRecordConsent)
            <x-ui.sheet name="record-consent" title="Record consent" lede="Record how {{ $client->full_name }} gave their POPIA consent." bag="popia"
                :action="route('client.consent.store', $client)" enctype="multipart/form-data"
                :form-data="'{ method: '.\Illuminate\Support\Js::from(old('method', 'signed_mandate')).' }'">
                <x-ui.select label="How consent was given" name="method" id="consent_method" x-model="method" :error="$errors->popia->first('method')">
                    @foreach (\App\Enums\Client\PopiaConsentMethod::cases() as $method)
                        <option value="{{ $method->value }}">{{ Str::headline($method->value) }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.input label="Date consent was given" name="consented_on" id="consent_date" type="date"
                    :value="old('consented_on', now()->toDateString())" max="{{ now()->toDateString() }}" :error="$errors->popia->first('consented_on')" />

                <x-ui.input label="Signed mandate" name="mandate" id="consent_mandate" type="file" accept=".pdf,.jpg,.jpeg,.png"
                    :error="$errors->popia->first('mandate')">
                </x-ui.input>
                <p class="form-hint" x-show="method === 'signed_mandate'">A signed mandate is required. PDF or image, up to 20 MB.</p>
                <p class="form-hint" x-show="method !== 'signed_mandate'" x-cloak>Optional. PDF or image, up to 20 MB.</p>

                <x-slot:footer>
                    <div class="d-flex align-items-center justify-content-between">
                        <x-ui.button variant="outline-secondary" x-on:click="close()">Cancel</x-ui.button>
                        <x-ui.button type="submit" variant="primary">Record consent</x-ui.button>
                    </div>
                </x-slot:footer>
            </x-ui.sheet>
        @endif

        @can('create', \App\Models\Matter::class)
            <x-matter.intake name="new-client-matter" :client="$client" :matter-types="$matterTypes" :courts="$courts" :attorneys="$attorneys" :action="route('matter.store')" />
        @endcan
    @endif

    @can('create', \App\Models\Client::class)
        @php
            $intake = $errors->client;

            $intakeConfig = [
                'type' => old('type', 'individual'),
                'idType' => old('id_type', \App\Enums\Client\IdentityType::SouthAfricanId->value),
                'consent' => (bool) old('popia_consent'),
                'consentMethod' => old('popia_method', \App\Enums\Client\PopiaConsentMethod::Signed->value),
                'matterTypeId' => (string) old('matter_type_id', ''),
                'matterTypes' => $matterTypes->mapWithKeys(fn ($type) => [
                    $type->getKey() => ['litigation' => (bool) $type->is_litigation],
                ]),
            ];
        @endphp

        <x-ui.sheet name="new-client" title="New client intake" lede="Register a client, and open their matter if you are ready to." bag="client" wide
            :action="route('client.store')" enctype="multipart/form-data" :form-data="'clientIntake('.\Illuminate\Support\Js::from($intakeConfig).')'">
            {{-- Planned: shown beside the title once drafts can be saved
            <span class="badge">draft autosaved</span>
            --}}
            {{-- Planned: needs fees and receipts
            <p class="text-meta">Consultation fee R1 500 · receipt issued on save</p>
            --}}

            <h3 class="text-meta text-uppercase mb-2">Identity</h3>

            {{-- The type comes first: it decides which of the fields below apply. --}}
            <x-ui.select label="Client type" name="type" x-model="type" :error="$intake->first('type')">
                <option value="individual">Individual</option>
                <option value="entity">Entity</option>
            </x-ui.select>

            <div x-show="type === 'individual'">
                <div class="row">
                    <div class="col-md-6">
                        <x-ui.input label="Full names" name="first_name" :value="old('first_name')" :error="$intake->first('first_name')" />
                    </div>
                    <div class="col-md-6">
                        <x-ui.input label="Surname" name="last_name" :value="old('last_name')" :error="$intake->first('last_name')" />
                    </div>
                </div>

                {{-- Clients come from several countries and do not always have their document with them,
                     so none of this is required; it can be added on the client later. --}}
                <div class="row">
                    <div class="col-md-6">
                        <x-ui.select label="Identity document" name="id_type" id="intake_id_type" x-model="idType" :error="$intake->first('id_type')">
                            @foreach (\App\Enums\Client\IdentityType::cases() as $identity)
                                <option value="{{ $identity->value }}">{{ $identity->label() }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                    <div class="col-md-6">
                        <x-ui.input label="ID or passport number" name="id_number" id="intake_id_number" :value="old('id_number')"
                            hint="Optional" :error="$intake->first('id_number')" />
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <x-ui.input label="Date of birth" name="date_of_birth" id="intake_date_of_birth" type="date" :value="old('date_of_birth')"
                            max="{{ now()->subDay()->toDateString() }}" hint="Optional. Read from an SA ID number when left empty."
                            :error="$intake->first('date_of_birth')" />
                    </div>
                </div>
            </div>

            <div x-show="type === 'entity'" x-cloak>
                <div class="row">
                    <div class="col-md-6">
                        <x-ui.input label="Registered name" name="entity_name" :value="old('entity_name')" :error="$intake->first('entity_name')" />
                    </div>
                    <div class="col-md-6">
                        <x-ui.input label="Registration number" name="registration_number" :value="old('registration_number')"
                            hint="e.g. 2011/123456/07" :error="$intake->first('registration_number')" />
                    </div>
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

            <hr>

            <h3 class="text-meta text-uppercase mb-2">FICA documents</h3>
            <p class="text-meta">Optional. Anything not on hand can be uploaded on the client later.</p>

            {{-- Each kind of client is asked for its own documents; the other kind's are disabled, so they are never sent. --}}
            @foreach (\App\Enums\Client\ClientType::cases() as $clientType)
                <div class="row" x-show="type === '{{ $clientType->value }}'" @if ($clientType !== \App\Enums\Client\ClientType::Individual) x-cloak @endif>
                    @foreach (\App\Enums\Client\FicaDocument::forType($clientType) as $document)
                        <div class="col-md-6">
                            <x-ui.input :label="$document->label()" name="fica[{{ $document->value }}]" id="intake_fica_{{ $document->value }}" type="file"
                                accept="{{ collect(array_keys(config('documents.allowed')))->map(fn ($extension) => '.'.$extension)->implode(',') }}"
                                x-bind:disabled="type !== '{{ $clientType->value }}'" :hint="$document->hint()"
                                :error="$intake->first('fica.'.$document->value)" />
                        </div>
                    @endforeach
                </div>
            @endforeach

            @can('create', \App\Models\Matter::class)
                <hr>

                <h3 class="text-meta text-uppercase mb-2">Matter</h3>
                <p class="text-meta">Choose a matter type to open the client's matter now, or leave it to register the client only.</p>

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
                        id="intake_popia_consent" name="popia_consent" value="1" x-model="consent">
                    <label class="form-check-label" for="intake_popia_consent">
                        Client confirms the information above is correct and consents to the firm processing their
                        personal information for the purposes of this matter (POPIA s.11). Consent is timestamped and
                        stored with the client record.
                    </label>
                    @if ($intake->has('popia_consent'))
                        <div class="invalid-feedback d-block">{{ $intake->first('popia_consent') }}</div>
                    @endif
                </div>

                {{-- Optional at intake; left unticked, consent is recorded on the client later. Disabled while
                     hidden, so nothing about a consent that was not given is sent. --}}
                <div class="row mt-3" x-show="consent" x-cloak>
                    <div class="col-md-6">
                        <x-ui.select label="How consent was given" name="popia_method" id="intake_popia_method" x-model="consentMethod"
                            x-bind:disabled="! consent" :error="$intake->first('popia_method')">
                            @foreach (\App\Enums\Client\PopiaConsentMethod::cases() as $method)
                                <option value="{{ $method->value }}">{{ Str::headline($method->value) }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                    <div class="col-md-6">
                        <x-ui.input label="Signed mandate" name="popia_mandate" id="intake_popia_mandate" type="file" accept=".pdf,.jpg,.jpeg,.png"
                            x-bind:disabled="! consent" :error="$intake->first('popia_mandate')" />
                        <p class="form-hint" x-show="consentMethod === 'signed_mandate'">Required for a signed mandate. PDF or image, up to 20 MB.</p>
                        <p class="form-hint" x-show="consentMethod !== 'signed_mandate'" x-cloak>Optional. PDF or image, up to 20 MB.</p>
                    </div>
                </div>
            @endcan

            <x-slot:footer>
                <div class="d-flex align-items-center justify-content-between">
                    <x-ui.button variant="outline-secondary" x-on:click="close()">Cancel</x-ui.button>

                    {{-- One save: the matter is opened too when a matter type was chosen. --}}
                    <x-ui.button type="submit" variant="primary">Save client</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.sheet>
    @endcan
@endsection
