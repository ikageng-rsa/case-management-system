{{-- The firm's details, as printed on its bills and shown in the app. --}}
@php
    $bag = 'firm_settings';
    $value = fn (string $field, mixed $default) => $errors->getBag($bag)->any() ? old($field, $default) : $default;
@endphp

<div class="card" style="max-width: 40rem;">
    <div class="card-header d-flex align-items-center justify-content-between">
        <div>
            <h3 class="card-title mb-0">Firm details</h3>
            <p class="text-meta mb-0">Printed at the top of every bill: the letterhead if there is one, otherwise these details.</p>
        </div>

        <x-ui.button variant="ghost-secondary" size="sm"
            x-data x-on:click="$dispatch('sheet-open', 'edit-firm-settings')">Edit</x-ui.button>
    </div>

    <div class="card-body">
        <div class="mb-3">
            @if ($letterhead = $firm->letterheadDataUri())
                <img src="{{ $letterhead }}" alt="The firm's letterhead" class="img-fluid border rounded">
            @else
                <div class="mb-0 text-secondary">No letterhead uploaded.</div>
            @endif
            <div class="text-meta">Letterhead</div>
        </div>

        <div class="mb-3">
            <div class="mb-0">{{ $firm->name }}</div>
            <div class="text-meta">Name</div>
        </div>

        <div class="mb-3">
            <div class="mb-0">{{ $firm->tagline ?? '—' }}</div>
            <div class="text-meta">Line under the name</div>
        </div>

        <div class="mb-3">
            @forelse ($firm->addressLines as $line)
                <div class="mb-0">{{ $line }}</div>
            @empty
                <div class="mb-0 text-secondary">—</div>
            @endforelse
            <div class="text-meta">Address</div>
        </div>

        <div class="mb-3">
            <div class="mb-0">{{ $firm->phone ?? '—' }}</div>
            <div class="text-meta">Phone</div>
        </div>

        <div class="mb-3">
            <div class="mb-0">{{ $firm->email ?? '—' }}</div>
            <div class="text-meta">Email</div>
        </div>

        <div class="mb-3">
            <div class="mb-0">{{ $firm->website ?? '—' }}</div>
            <div class="text-meta">Website</div>
        </div>

        <div>
            <div class="mb-0">{{ $firm->registrationNumber ?? '—' }}</div>
            <div class="text-meta">Company registration number</div>
        </div>
    </div>
</div>

<x-ui.sheet name="edit-firm-settings" title="Edit firm details" :bag="$bag"
    :action="route('settings.firm.update')" method="PUT" enctype="multipart/form-data">
    <x-ui.input label="Letterhead" name="letterhead" id="firm_letterhead" type="file" accept=".png,.jpg,.jpeg"
        hint="A PNG or JPG of the letterhead strip, up to 2 MB. Printed full width at the top of every bill."
        :error="$errors->getBag($bag)->first('letterhead')" />

    @if ($firm->letterheadPath)
        <input type="hidden" name="remove_letterhead" value="0">
        <label class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="remove_letterhead" value="1">
            <span class="form-check-label">Remove the letterhead and print the details below instead</span>
        </label>
    @endif

    <x-ui.input label="Name" name="name" id="firm_name" :value="$value('name', $firm->name)"
        hint="As it should read on bills and in the app." :error="$errors->getBag($bag)->first('name')" />

    <x-ui.input label="Line under the name" name="tagline" id="firm_tagline" :value="$value('tagline', $firm->tagline)"
        hint="Optional, e.g. Attorneys Inc. Shown under the name in the app." :error="$errors->getBag($bag)->first('tagline')" />

    <div class="mb-3">
        <label class="form-label form-label-mono" for="firm_address">Address</label>
        <textarea class="form-control @error('address', $bag) is-invalid @enderror" id="firm_address" name="address" rows="4">{{ $value('address', implode("\n", $firm->addressLines)) }}</textarea>
        @error('address', $bag)
            <div class="invalid-feedback">{{ $message }}</div>
        @else
            <div class="form-hint">One line of the address per line.</div>
        @enderror
    </div>

    <x-ui.input label="Phone" name="phone" id="firm_phone" :value="$value('phone', $firm->phone)"
        :error="$errors->getBag($bag)->first('phone')" />

    <x-ui.input label="Email" name="email" id="firm_email" type="email" :value="$value('email', $firm->email)"
        :error="$errors->getBag($bag)->first('email')" />

    <x-ui.input label="Website" name="website" id="firm_website" :value="$value('website', $firm->website)"
        :error="$errors->getBag($bag)->first('website')" />

    <x-ui.input label="Company registration number" name="registration_number" id="firm_registration" :value="$value('registration_number', $firm->registrationNumber)"
        :error="$errors->getBag($bag)->first('registration_number')" />

    <x-slot:footer>
        <div class="d-flex align-items-center justify-content-between">
            <x-ui.button variant="outline-secondary" size="sm" x-on:click="close()">Cancel</x-ui.button>
            <x-ui.button type="submit" variant="primary" size="sm">Save details</x-ui.button>
        </div>
    </x-slot:footer>
</x-ui.sheet>
