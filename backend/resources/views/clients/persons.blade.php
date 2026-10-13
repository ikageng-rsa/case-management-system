@php
    // Another form's failed input must not leak into this one.
    $personInput = fn (string $bag, string $field, mixed $default) => $errors->getBag($bag)->any() ? old($field, $default) : $default;

    $maskedMobile = fn ($person) => $person->mobile ? \App\Services\Clients\MaskIdentifier::mobile($person->mobile) : null;
    $maskedEmail = fn ($person) => $person->email ? \App\Services\Clients\MaskIdentifier::email($person->email) : null;
@endphp

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <div>
            <h3 class="card-title mb-0">Contact persons</h3>
            <p class="text-meta mb-0">The people to call at {{ $client->full_name }}, the first contact and any alternatives</p>
        </div>

        @if ($canEditClient)
            <x-ui.button variant="primary" icon="plus" size="sm"
                x-data x-on:click="$dispatch('sheet-open', 'new-person')">Add person</x-ui.button>
        @endif
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>Person</th>
                    <th>Mobile</th>
                    <th>Email</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($contactPersons as $person)
                    <tr>
                        <td>
                            <div class="fw-bold">{{ $person->name }}</div>
                            <div class="text-meta">
                                {{ $person->role ?: 'No role given' }} ·
                                {{ $person->is_primary ? 'First contact' : 'Alternative' }}
                            </div>
                        </td>
                        <td>
                            @if ($person->mobile)
                                <x-client.reveal :masked="$maskedMobile($person)" :target="'person:'.$person->getKey().':mobile'" :client="$client" :can-reveal="$canReveal" />
                            @else
                                <span class="text-secondary">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($person->email)
                                <x-client.reveal :masked="$maskedEmail($person)" :target="'person:'.$person->getKey().':email'" :client="$client" :can-reveal="$canReveal" />
                            @else
                                <span class="text-secondary">—</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @if ($canEditClient)
                                <x-ui.button variant="ghost-secondary" size="sm"
                                    x-data x-on:click="$dispatch('sheet-open', 'edit-person-{{ $person->getKey() }}')">Edit</x-ui.button>

                                <form method="POST" action="{{ route('client.persons.destroy', [$client, $person]) }}" class="d-inline"
                                    data-confirm="Remove {{ $person->name }} as a contact person? This cannot be undone."
                                    data-confirm-title="Remove contact person" data-confirm-action="Remove" data-confirm-tone="danger" data-confirm-icon="user-minus">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" variant="ghost-danger" size="sm">Remove</x-ui.button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-secondary py-4">No contact persons yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($canEditClient)
    <x-ui.sheet name="new-person" title="Add a contact person" :lede="'At '.$client->full_name" bag="person_new"
        :action="route('client.persons.store', $client)">
        <x-ui.input label="Name" name="name" id="new_person_name" :value="old('name')" :error="$errors->person_new->first('name')" />
        <x-ui.input label="Role" name="role" id="new_person_role" :value="old('role')" before="Optional" hint="e.g. Managing director" :error="$errors->person_new->first('role')" />
        <x-ui.input label="Mobile" name="mobile" id="new_person_mobile" type="tel" :value="old('mobile')" :error="$errors->person_new->first('mobile')" />
        <x-ui.input label="Email" name="email" id="new_person_email" type="email" :value="old('email')" :error="$errors->person_new->first('email')"
            hint="A mobile number or an email address is needed." />

        <label class="form-check">
            <input class="form-check-input" type="checkbox" name="is_primary" value="1" @checked(old('is_primary'))>
            <span class="form-check-label">First contact (the others are alternatives)</span>
        </label>

        <x-slot:footer>
            <div class="d-flex align-items-center justify-content-between">
                <x-ui.button variant="outline-secondary" x-on:click="close()">Cancel</x-ui.button>
                <x-ui.button type="submit" variant="primary">Add person</x-ui.button>
            </div>
        </x-slot:footer>
    </x-ui.sheet>

    @foreach ($contactPersons as $person)
        @php($bag = 'person_'.$person->getKey())

        <x-ui.sheet name="edit-person-{{ $person->getKey() }}" title="Edit contact person" :lede="$person->name" :bag="$bag" method="PATCH"
            :action="route('client.persons.update', [$client, $person])">
            <x-ui.input label="Name" name="name" id="edit_person_name_{{ $person->getKey() }}" :value="$personInput($bag, 'name', $person->name)" :error="$errors->getBag($bag)->first('name')" />
            <x-ui.input label="Role" name="role" id="edit_person_role_{{ $person->getKey() }}" :value="$personInput($bag, 'role', $person->role)" before="Optional" :error="$errors->getBag($bag)->first('role')" />
            <x-ui.input label="Mobile" name="mobile" id="edit_person_mobile_{{ $person->getKey() }}" type="tel" :value="$personInput($bag, 'mobile', '')"
                :placeholder="$maskedMobile($person)" :hint="$person->mobile ? 'Leave empty to keep the current number.' : null" :error="$errors->getBag($bag)->first('mobile')" />
            <x-ui.input label="Email" name="email" id="edit_person_email_{{ $person->getKey() }}" type="email" :value="$personInput($bag, 'email', '')"
                :placeholder="$maskedEmail($person)" :hint="$person->email ? 'Leave empty to keep the current address.' : null" :error="$errors->getBag($bag)->first('email')" />

            <label class="form-check">
                <input class="form-check-input" type="checkbox" name="is_primary" value="1" @checked($person->is_primary) @disabled($person->is_primary)>
                <span class="form-check-label">First contact</span>
            </label>
            @if ($person->is_primary)
                <p class="form-hint">To change the first contact, make another person the first contact.</p>
            @endif

            <x-slot:footer>
                <div class="d-flex align-items-center justify-content-between">
                    <x-ui.button variant="outline-secondary" x-on:click="close()">Cancel</x-ui.button>
                    <x-ui.button type="submit" variant="primary">Save person</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.sheet>
    @endforeach
@endif
