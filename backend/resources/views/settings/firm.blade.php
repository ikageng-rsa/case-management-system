{{-- The firm's details, as printed on its bills and shown in the app. --}}
@php
    $bag = 'firm_settings';
    $value = fn (string $field, mixed $default) => $errors->getBag($bag)->any() ? old($field, $default) : $default;
@endphp

<div class="card" style="max-width: 40rem;">
    <div class="card-header d-flex align-items-center justify-content-between">
        <div>
            <h3 class="card-title mb-0">Firm details</h3>
            <p class="text-meta mb-0">Printed at the top of every bill.</p>
        </div>

        <x-ui.button variant="ghost-secondary" size="sm"
            x-data x-on:click="$dispatch('sheet-open', 'edit-firm-settings')">Edit</x-ui.button>
    </div>

    <div class="card-body">
        <div class="mb-3">
            <div class="mb-0">{{ $firm->name }}</div>
            <div class="text-meta">Name</div>
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

        <div>
            <div class="mb-0">{{ $firm->email ?? '—' }}</div>
            <div class="text-meta">Email</div>
        </div>
    </div>
</div>

<x-ui.sheet name="edit-firm-settings" title="Edit firm details" :bag="$bag"
    :action="route('settings.firm.update')" method="PUT">
    <x-ui.input label="Name" name="name" id="firm_name" :value="$value('name', $firm->name)"
        hint="As it should read on bills." :error="$errors->getBag($bag)->first('name')" />

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

    <x-slot:footer>
        <div class="d-flex align-items-center justify-content-between">
            <x-ui.button variant="outline-secondary" size="sm" x-on:click="close()">Cancel</x-ui.button>
            <x-ui.button type="submit" variant="primary" size="sm">Save details</x-ui.button>
        </div>
    </x-slot:footer>
</x-ui.sheet>
