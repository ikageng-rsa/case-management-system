@php
    $kinds = collect(\App\Enums\Client\ContactKind::cases())->reject(fn ($kind) => $kind === \App\Enums\Client\ContactKind::ContactPerson);

    $maskedValue = fn (\App\Models\ClientContact $contact) => match ($contact->kind) {
        \App\Enums\Client\ContactKind::Mobile => \App\Services\Clients\MaskIdentifier::mobile($contact->value),
        \App\Enums\Client\ContactKind::Email => \App\Services\Clients\MaskIdentifier::email($contact->value),
        default => \App\Services\Clients\MaskIdentifier::address($contact->value),
    };

    $kindLabel = fn (\App\Enums\Client\ContactKind $kind) => Str::headline(str_replace('-', ' ', $kind->value));

    // Another form's failed input must not leak into this one.
    $contactInput = fn (string $bag, string $field, mixed $default) => $errors->getBag($bag)->any() ? old($field, $default) : $default;
@endphp

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <div>
            <h3 class="card-title mb-0">Contacts</h3>
            <p class="text-meta mb-0">{{ $client->contacts->count() }} {{ Str::plural('way', $client->contacts->count()) }} of reaching {{ $client->full_name }}</p>
        </div>

        @if ($canEditClient)
            <x-ui.button variant="primary" icon="plus" size="sm"
                x-data x-on:click="$dispatch('sheet-open', 'new-contact')">Add contact</x-ui.button>
        @endif
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>Kind</th>
                    <th>Contact</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($client->contacts->sortBy([['kind', 'asc'], ['is_primary', 'desc']]) as $contact)
                    <tr>
                        <td class="text-meta">{{ $kindLabel($contact->kind) }}</td>
                        <td>
                            <x-client.reveal :masked="$maskedValue($contact)" :target="'contact:'.$contact->getKey()" :client="$client" :can-reveal="$canReveal" />
                            @if ($contact->is_primary)
                                <x-ui.stage-badge tone="info" class="ms-2">Primary</x-ui.stage-badge>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @if ($canEditClient)
                                <x-ui.button variant="ghost-secondary" size="sm"
                                    x-data x-on:click="$dispatch('sheet-open', 'edit-contact-{{ $contact->getKey() }}')">Edit</x-ui.button>

                                <form method="POST" action="{{ route('client.contacts.destroy', [$client, $contact]) }}" class="d-inline"
                                    data-confirm="Remove this {{ strtolower($kindLabel($contact->kind)) }} contact? This cannot be undone."
                                    data-confirm-title="Remove contact" data-confirm-action="Remove" data-confirm-tone="danger" data-confirm-icon="trash">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" variant="ghost-danger" size="sm">Remove</x-ui.button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center text-secondary py-4">No contacts on file.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($canEditClient)
    <x-ui.sheet name="new-contact" title="Add a contact" :lede="'For '.$client->full_name" bag="contact_new"
        :action="route('client.contacts.store', $client)">
        <x-ui.select label="Kind" name="kind" id="new_contact_kind" :error="$errors->contact_new->first('kind')"
            :hint="$client->isEntity() ? null : 'An individual has one contact of each kind.'">
            @foreach ($kinds as $kind)
                <option value="{{ $kind->value }}" @selected(old('kind') === $kind->value)>{{ $kindLabel($kind) }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.input label="Value" name="value" id="new_contact_value" :value="old('value')" :error="$errors->contact_new->first('value')"
            hint="A mobile number, email address or address." />

        <label class="form-check">
            <input class="form-check-input" type="checkbox" name="is_primary" value="1" @checked(old('is_primary'))>
            <span class="form-check-label">Make this the primary contact of its kind</span>
        </label>

        <x-slot:footer>
            <div class="d-flex align-items-center justify-content-between">
                <x-ui.button variant="outline-secondary" x-on:click="close()">Cancel</x-ui.button>
                <x-ui.button type="submit" variant="primary">Add contact</x-ui.button>
            </div>
        </x-slot:footer>
    </x-ui.sheet>

    @foreach ($client->contacts as $contact)
        @php($bag = 'contact_'.$contact->getKey())

        <x-ui.sheet name="edit-contact-{{ $contact->getKey() }}" :title="'Edit '.strtolower($kindLabel($contact->kind))" :lede="$client->full_name" :bag="$bag" method="PATCH"
            :action="route('client.contacts.update', [$client, $contact])">
            <x-ui.input label="Value" name="value" id="edit_contact_value_{{ $contact->getKey() }}" :value="$contactInput($bag, 'value', '')"
                :placeholder="$maskedValue($contact)" hint="Leave empty to keep the current value." :error="$errors->getBag($bag)->first('value')" />

            <label class="form-check">
                <input class="form-check-input" type="checkbox" name="is_primary" value="1" @checked($contact->is_primary) @disabled($contact->is_primary)>
                <span class="form-check-label">Primary contact of its kind</span>
            </label>
            @if ($contact->is_primary)
                <p class="form-hint">To change the primary, make another contact of this kind the primary one.</p>
            @endif

            <x-slot:footer>
                <div class="d-flex align-items-center justify-content-between">
                    <x-ui.button variant="outline-secondary" x-on:click="close()">Cancel</x-ui.button>
                    <x-ui.button type="submit" variant="primary">Save contact</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.sheet>
    @endforeach
@endif
