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
                        <x-ui.button variant="outline-secondary" size="sm">Edit</x-ui.button>
                        <x-ui.button variant="primary" icon="plus" size="sm">New matter</x-ui.button>
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

    @can('create', \App\Models\Client::class)
        <x-ui.sheet name="new-client" title="New client intake" lede="Register a client before opening a matter." bag="client">
            <form method="POST" action="{{ route('client.store') }}" x-data="{ type: @js(old('type', 'individual')) }">
                @csrf

                <x-ui.select label="Client type" name="type" x-model="type" :error="$errors->client->first('type')">
                    <option value="individual">Individual</option>
                    <option value="entity">Entity</option>
                </x-ui.select>

                <div x-show="type === 'individual'">
                    <x-ui.input label="First name" name="first_name" :value="old('first_name')" :error="$errors->client->first('first_name')" />
                    <x-ui.input label="Last name" name="last_name" :value="old('last_name')" :error="$errors->client->first('last_name')" />
                    <x-ui.input label="ID number" name="id_number" :value="old('id_number')" :error="$errors->client->first('id_number')" />
                </div>

                <div x-show="type === 'entity'" x-cloak>
                    <x-ui.input label="Registered name" name="entity_name" :value="old('entity_name')" :error="$errors->client->first('entity_name')" />
                    <x-ui.input label="Registration number" name="registration_number" :value="old('registration_number')"
                        hint="e.g. 2011/123456/07" :error="$errors->client->first('registration_number')" />
                </div>

                <x-ui.input label="Mobile" name="mobile" type="tel" :value="old('mobile')" before="Optional" :error="$errors->client->first('mobile')" />
                <x-ui.input label="Email" name="email" type="email" :value="old('email')" before="Optional" :error="$errors->client->first('email')" />

                <x-ui.button type="submit" variant="primary">Register client</x-ui.button>
            </form>
        </x-ui.sheet>
    @endcan
@endsection
