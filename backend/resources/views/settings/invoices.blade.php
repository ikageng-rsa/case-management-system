{{-- How bills look when printed, and how a statement asks to be paid. --}}
@php
    $bag = 'invoice_settings';
    $value = fn (string $field, mixed $default) => $errors->getBag($bag)->any() ? old($field, $default) : $default;
    $chosenTheme = $value('theme', $invoices->theme->value);
    $chosenAccent = $value('accent_colour', $invoices->accentColour);
@endphp

<div class="card" style="max-width: 40rem; --invoice-accent: {{ $invoices->accentColour }};">
    <div class="card-header d-flex align-items-center justify-content-between">
        <div>
            <h3 class="card-title mb-0">Invoices</h3>
            <p class="text-meta mb-0">How bills look when printed. Statements to clients also say how to pay.</p>
        </div>

        <x-ui.button variant="ghost-secondary" size="sm"
            x-data x-on:click="$dispatch('sheet-open', 'edit-invoice-settings')">Edit</x-ui.button>
    </div>

    <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-4">
            <x-billing.invoice-theme :theme="$invoices->theme" style="width: 8rem;" />
            <div>
                <div class="mb-0">{{ $invoices->theme->label() }}</div>
                <div class="text-meta">{{ $invoices->theme->description() }}</div>
                <div class="text-meta mt-1">
                    <span class="d-inline-block rounded-circle align-middle me-1" style="width: .75rem; height: .75rem; background: {{ $invoices->accentColour }};"></span>
                    Accent {{ $invoices->accentColour }}
                </div>
            </div>
        </div>

        <h4 class="text-meta text-uppercase mb-2">Payment, on statements</h4>

        <div class="mb-3">
            <div class="mb-0">{{ $invoices->paymentTerms ?? '—' }}</div>
            <div class="text-meta">Terms</div>
        </div>

        <div class="mb-3">
            @if ($invoices->hasBankAccount())
                <div class="mb-0">{{ $invoices->accountName ?? $firm->name }}</div>
                <div class="mb-0">{{ $invoices->bankName }} · {{ $invoices->accountType ?? 'Account' }} {{ $invoices->accountNumber }}</div>
                @if ($invoices->branchCode)
                    <div class="mb-0">Branch code {{ $invoices->branchCode }}</div>
                @endif
            @else
                <div class="mb-0 text-secondary">No account given, so statements do not say where to pay.</div>
            @endif
            <div class="text-meta">Account to pay to</div>
        </div>

        <div>
            <div class="mb-0">The statement's number, e.g. INV/{{ now()->year }}/0001</div>
            <div class="text-meta">Payment reference</div>
        </div>
    </div>
</div>

<x-ui.sheet name="edit-invoice-settings" title="Edit invoice settings" :bag="$bag"
    :action="route('settings.invoices.update')" method="PUT" wide>
    <div x-data="{ theme: @js($chosenTheme), accent: @js($chosenAccent) }" x-bind:style="'--invoice-accent: ' + accent">
        <label class="form-label form-label-mono">Theme</label>
        <div class="row g-2 mb-1" role="radiogroup" aria-label="Theme">
            @foreach ($themes as $theme)
                <div class="col-4">
                    <label class="d-block text-center" style="cursor: pointer;">
                        <input type="radio" name="theme" value="{{ $theme->value }}" class="visually-hidden" x-model="theme">
                        <span class="d-block rounded-3 p-1 border border-2"
                            x-bind:style="'border-color: ' + (theme === @js($theme->value) ? 'var(--tblr-primary)' : 'transparent')">
                            <x-billing.invoice-theme :theme="$theme" />
                        </span>
                        <span class="d-block mt-1" x-bind:class="theme === @js($theme->value) ? 'text-primary' : ''">{{ $theme->label() }}</span>
                    </label>
                </div>
            @endforeach
        </div>
        <p class="form-hint mb-3" x-text="{{ Js::from(collect($themes)->mapWithKeys(fn ($theme) => [$theme->value => $theme->description()])) }}[theme]"></p>

        <div class="mb-3">
            <label class="form-label form-label-mono" for="invoice_accent">Accent colour</label>
            <div class="d-flex align-items-center gap-2">
                <input type="color" class="form-control form-control-color" id="invoice_accent" name="accent_colour" x-model="accent">
                <span class="text-meta" x-text="accent.toUpperCase()"></span>
            </div>
            @if ($errors->getBag($bag)->has('accent_colour'))
                <div class="text-danger small">{{ $errors->getBag($bag)->first('accent_colour') }}</div>
            @endif
        </div>
    </div>

    <h4 class="text-meta text-uppercase mt-4 mb-2">Payment, printed on statements</h4>

    <div class="mb-3">
        <label class="form-label form-label-mono" for="invoice_terms">Terms</label>
        <textarea class="form-control @error('payment_terms', $bag) is-invalid @enderror" id="invoice_terms" name="payment_terms" rows="2">{{ $value('payment_terms', $invoices->paymentTerms) }}</textarea>
        @error('payment_terms', $bag)
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <x-ui.input label="Account name" name="account_name" id="invoice_account_name" :value="$value('account_name', $invoices->accountName)"
        hint="Leave empty to print the firm's name." :error="$errors->getBag($bag)->first('account_name')" />

    <x-ui.input label="Bank" name="bank_name" id="invoice_bank" :value="$value('bank_name', $invoices->bankName)"
        :error="$errors->getBag($bag)->first('bank_name')" />

    <div class="row g-2">
        <div class="col-7">
            <x-ui.input label="Account number" name="account_number" id="invoice_account_number" inputmode="numeric"
                :value="$value('account_number', $invoices->accountNumber)" :error="$errors->getBag($bag)->first('account_number')" />
        </div>
        <div class="col-5">
            <x-ui.input label="Branch code" name="branch_code" id="invoice_branch" :value="$value('branch_code', $invoices->branchCode)"
                :error="$errors->getBag($bag)->first('branch_code')" />
        </div>
    </div>

    <x-ui.input label="Account type" name="account_type" id="invoice_account_type" :value="$value('account_type', $invoices->accountType)"
        hint="e.g. Business cheque" :error="$errors->getBag($bag)->first('account_type')" />

    <p class="form-hint">Clients are asked to quote the statement's number as the payment reference.</p>

    <x-slot:footer>
        <div class="d-flex align-items-center justify-content-between">
            <x-ui.button variant="outline-secondary" size="sm" x-on:click="close()">Cancel</x-ui.button>
            <x-ui.button type="submit" variant="primary" size="sm">Save settings</x-ui.button>
        </div>
    </x-slot:footer>
</x-ui.sheet>
