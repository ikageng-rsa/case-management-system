{{-- How the firm bills, shown read-only with an Edit sheet. --}}
@php
    $bag = 'billing_settings';
    $percent = fn (float $value) => rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    $value = fn (string $field, mixed $default) => $errors->getBag($bag)->any() ? old($field, $default) : $default;
@endphp

<div class="card" style="max-width: 40rem;">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title mb-0">Billing terms</h3>

        <x-ui.button variant="ghost-secondary" size="sm"
            x-data x-on:click="$dispatch('sheet-open', 'edit-billing-settings')">Edit</x-ui.button>
    </div>

    <div class="card-body">
        <h4 class="text-meta text-uppercase mb-2">Fee agreements</h4>

        <div class="mb-3">
            <div class="mb-0">{{ $percent($billing->contingencyPercent) }}%</div>
            <div class="text-meta">Contingency fee, of what the client receives once a state matter is finalised</div>
        </div>

        <div class="mb-4">
            <div class="mb-0">{{ $percent($billing->estatePercent) }}%</div>
            <div class="text-meta">Estate fee, of the estate funds after administration and distribution</div>
        </div>

        <h4 class="text-meta text-uppercase mb-2">Statements</h4>

        <div class="mb-3">
            <div class="mb-0">{{ $percent($billing->drawingFeePercent) }}% on {{ strtolower($billing->drawingFeeBase->label()) }}</div>
            <div class="text-meta">Drawing fee, added to every statement and bill of cost and shown under fees</div>
        </div>

        <div>
            <div class="mb-0">{{ $billing->billingTiming->label() }}</div>
            <div class="text-meta">When statements are drawn. A drawn statement is a draft until someone issues it</div>
        </div>
    </div>
</div>

<x-ui.sheet name="edit-billing-settings" title="Edit billing settings" :bag="$bag"
    :action="route('billing-settings.update')" method="PUT">
    <x-ui.input label="Contingency fee (%)" name="contingency_percent" id="contingency_percent" type="number" step="0.01" min="0" max="100"
        :value="$value('contingency_percent', $percent($billing->contingencyPercent))"
        hint="Of what the client receives once a state matter is finalised."
        :error="$errors->getBag($bag)->first('contingency_percent')" />

    <x-ui.input label="Estate fee (%)" name="estate_percent" id="estate_percent" type="number" step="0.01" min="0" max="100"
        :value="$value('estate_percent', $percent($billing->estatePercent))"
        hint="Of the estate funds, after administration and distribution."
        :error="$errors->getBag($bag)->first('estate_percent')" />

    <x-ui.input label="Drawing fee (%)" name="drawing_fee_percent" id="drawing_fee_percent" type="number" step="0.01" min="0" max="100"
        :value="$value('drawing_fee_percent', $percent($billing->drawingFeePercent))"
        hint="Added to every statement and bill of cost, and shown under fees."
        :error="$errors->getBag($bag)->first('drawing_fee_percent')" />

    <x-ui.select label="Drawing fee is charged on" name="drawing_fee_base" id="drawing_fee_base" :error="$errors->getBag($bag)->first('drawing_fee_base')">
        @foreach ($bases as $base)
            <option value="{{ $base->value }}" @selected($value('drawing_fee_base', $billing->drawingFeeBase->value) === $base->value)>{{ $base->label() }}</option>
        @endforeach
    </x-ui.select>

    <x-ui.select label="Statements are drawn" name="billing_timing" id="billing_timing" :error="$errors->getBag($bag)->first('billing_timing')"
        hint="A drawn statement is always a draft until someone issues it.">
        @foreach ($timings as $timing)
            <option value="{{ $timing->value }}" @selected($value('billing_timing', $billing->billingTiming->value) === $timing->value)>{{ $timing->label() }}</option>
        @endforeach
    </x-ui.select>

    <x-slot:footer>
        <div class="d-flex align-items-center justify-content-between">
            <x-ui.button variant="outline-secondary" size="sm" x-on:click="close()">Cancel</x-ui.button>
            <x-ui.button type="submit" variant="primary" size="sm">Save settings</x-ui.button>
        </div>
    </x-slot:footer>
</x-ui.sheet>
