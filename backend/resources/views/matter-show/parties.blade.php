<div class="card mb-3">
    <div class="card-body">
        <h3 class="card-title text-meta text-uppercase mb-3">Client</h3>

        <div class="mb-3">
            <div class="mb-0">{{ $matter->client->full_name }}</div>
            <div class="text-meta">{{ ucfirst($matter->client->type->value) }}</div>
        </div>

        @foreach ($contacts as $contact)
            <div @class(['mb-3' => ! $loop->last])>
                <div class="mb-0">{{ $contact->value }}</div>
                <div class="text-meta">{{ str_replace('-', ' ', $contact->kind->value) }}{{ $contact->is_primary ? ' · primary' : '' }}</div>
            </div>
        @endforeach

        @if ($matter->court)
            <hr>
            <div class="mb-0">{{ $matter->court->label }}</div>
            <div class="text-meta">Court</div>
        @endif
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h3 class="card-title text-meta text-uppercase mb-0">Parties</h3>

            @can('update', $matter)
                <x-ui.button variant="ghost-secondary" icon="plus" size="sm" aria-label="Add a party"
                    x-data x-on:click="$dispatch('sheet-open', 'new-party')" />
            @endcan
        </div>

        @forelse ($matterParties as $party)
            <div @class(['d-flex align-items-center justify-content-between gap-2', 'mb-3' => ! $loop->last])>
                <div>
                    <div class="mb-0">{{ $party->name }}</div>
                    <div class="text-meta">{{ $party->role->label() }}@if ($party->representative) · represented by {{ $party->representative }}@endif</div>
                </div>

                @can('update', $matter)
                    <div class="text-nowrap">
                        <x-ui.button variant="ghost-secondary" size="sm"
                            x-data x-on:click="$dispatch('sheet-open', 'edit-party-{{ $party->getKey() }}')">Edit</x-ui.button>

                        <form method="POST" action="{{ route('matter.parties.destroy', [$matter, $party]) }}" class="d-inline"
                            data-confirm="Remove {{ $party->name }} from this matter?"
                            data-confirm-title="Remove party" data-confirm-action="Remove" data-confirm-tone="danger" data-confirm-icon="user-minus">
                            @csrf
                            @method('DELETE')
                            <x-ui.button type="submit" variant="ghost-danger" size="sm">Remove</x-ui.button>
                        </form>
                    </div>
                @endcan
            </div>
        @empty
            <p class="text-secondary mb-0">No other parties recorded.</p>
        @endforelse
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <h3 class="card-title text-meta text-uppercase mb-3">Assignments</h3>

        @forelse ($matter->assignments as $assignment)
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <div class="mb-0">{{ $assignment->user->name }}</div>
                    <div class="text-meta">{{ ucfirst($assignment->capacity->value) }} · since {{ $assignment->assigned_at->format('d M Y') }}</div>
                </div>

                @can('assign', $matter)
                    <form method="POST" action="{{ route('matter.unassign', $matter) }}"
                        data-confirm="Remove {{ $assignment->user->name }} as {{ $assignment->capacity->value }}?"
                        data-confirm-title="Remove from matter" data-confirm-action="Remove" data-confirm-tone="danger" data-confirm-icon="user-minus">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="user_id" value="{{ $assignment->user_id }}">
                        <input type="hidden" name="capacity" value="{{ $assignment->capacity->value }}">
                        <x-ui.button type="submit" variant="ghost-danger" size="sm">Remove</x-ui.button>
                    </form>
                @endcan
            </div>
        @empty
            <p class="text-secondary">Nobody is assigned yet.</p>
        @endforelse
    </div>
</div>

@can('update', $matter)
    @php
        // Another form's failed input must not leak into this one.
        $partyInput = fn (string $bag, string $field, mixed $default) => $errors->getBag($bag)->any() ? old($field, $default) : $default;
    @endphp

    <x-ui.sheet name="new-party" title="Add a party" lede="Someone else in this matter, besides the client." bag="party_new"
        :action="route('matter.parties.store', $matter)">
        <x-ui.input label="Name" name="name" id="new_party_name" :value="old('name')" :error="$errors->party_new->first('name')" />

        <x-ui.select label="Role" name="role" id="new_party_role" :error="$errors->party_new->first('role')">
            @foreach ($partyRoles as $role)
                <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->label() }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.input label="Represented by" name="representative" id="new_party_representative" before="Optional"
            hint="Their attorneys or counsel." :value="old('representative')" :error="$errors->party_new->first('representative')" />

        <x-slot:footer>
            <div class="d-flex align-items-center justify-content-between">
                <x-ui.button variant="outline-secondary" x-on:click="close()">Cancel</x-ui.button>
                <x-ui.button type="submit" variant="primary">Add party</x-ui.button>
            </div>
        </x-slot:footer>
    </x-ui.sheet>

    @foreach ($matterParties as $party)
        @php($bag = 'party_'.$party->getKey())

        <x-ui.sheet name="edit-party-{{ $party->getKey() }}" title="Edit party" :lede="$party->name" :bag="$bag" method="PATCH"
            :action="route('matter.parties.update', [$matter, $party])">
            <x-ui.input label="Name" name="name" id="edit_party_name_{{ $party->getKey() }}"
                :value="$partyInput($bag, 'name', $party->name)" :error="$errors->getBag($bag)->first('name')" />

            <x-ui.select label="Role" name="role" id="edit_party_role_{{ $party->getKey() }}" :error="$errors->getBag($bag)->first('role')">
                @foreach ($partyRoles as $role)
                    <option value="{{ $role->value }}" @selected($partyInput($bag, 'role', $party->role->value) === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.input label="Represented by" name="representative" id="edit_party_representative_{{ $party->getKey() }}" before="Optional"
                :value="$partyInput($bag, 'representative', $party->representative)" :error="$errors->getBag($bag)->first('representative')" />

            <x-slot:footer>
                <div class="d-flex align-items-center justify-content-between">
                    <x-ui.button variant="outline-secondary" x-on:click="close()">Cancel</x-ui.button>
                    <x-ui.button type="submit" variant="primary">Save party</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.sheet>
    @endforeach
@endcan
