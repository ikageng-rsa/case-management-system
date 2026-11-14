@php
    $rand = fn (?int $cents) => $cents === null ? '—' : 'R '.number_format($cents / 100, 2, '.', ' ');
    $percent = fn (float $value) => rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    $tones = ['active' => 'success', 'failed' => 'danger', 'finalised' => 'neutral'];
    $agreementErrors = $errors->getBag('fee_agreement');
@endphp

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h3 class="card-title text-meta text-uppercase mb-0">Fee agreement</h3>

            @unless ($activeAgreement)
                <x-ui.button variant="primary" icon="plus" size="sm"
                    x-data x-on:click="$dispatch('sheet-open', 'new-fee-agreement')">Record fee agreement</x-ui.button>
            @endunless
        </div>

        @if ($agreementErrors->has('fee_agreement'))
            <div class="alert alert-danger">{{ $agreementErrors->first('fee_agreement') }}</div>
        @endif

        @if ($activeAgreement)
            <div class="d-flex align-items-start justify-content-between gap-3">
                <div>
                    <div class="mb-0">{{ $activeAgreement->label() }}</div>
                    <div class="text-meta">
                        {{ $activeAgreement->signed_on ? 'Signed '.$activeAgreement->signed_on->format('j M Y') : 'Not yet signed' }}
                        @if ($activeAgreement->recordedBy) · recorded by {{ $activeAgreement->recordedBy->name }} @endif
                    </div>
                    @if ($activeAgreement->signedCopy())
                        <a href="{{ route('fee-agreement.signed-copy', $activeAgreement) }}" class="text-meta">Signed copy</a>
                    @endif
                </div>

                <div class="text-nowrap">
                    @if ($activeAgreement->kind === \App\Enums\Billing\FeeAgreementKind::Contingency)
                        <form method="POST" action="{{ route('fee-agreement.fail', $activeAgreement) }}" class="d-inline"
                            data-confirm="Mark the claim failed? The firm takes no share, and a mandate can then be recorded to bill the client by statement."
                            data-confirm-title="Claim failed" data-confirm-action="Mark failed" data-confirm-tone="danger" data-confirm-icon="x">
                            @csrf
                            <x-ui.button type="submit" variant="ghost-danger" size="sm">Claim failed</x-ui.button>
                        </form>
                    @endif

                    @if ($activeAgreement->kind->isPercentage())
                        <x-ui.button variant="outline-secondary" size="sm"
                            x-data x-on:click="$dispatch('sheet-open', 'finalise-fee-agreement')">Finalise</x-ui.button>
                    @endif
                </div>
            </div>
        @else
            <p class="text-secondary mb-0">No fee agreement in place. Matters of this type are usually charged under {{ strtolower($suggestedFeeKind->label()) }}{{ $suggestedFeeKind->isPercentage() ? ' at '.$percent($defaultPercents[$suggestedFeeKind->value]).'%' : '' }}.</p>
        @endif
    </div>
</div>

@if ($feeAgreements->reject->isActive()->isNotEmpty())
    <div class="card mb-3">
        <div class="card-body">
            <h3 class="card-title text-meta text-uppercase mb-3">Earlier agreements</h3>

            @foreach ($feeAgreements->reject->isActive() as $agreement)
                <div @class(['d-flex align-items-center justify-content-between gap-2', 'mb-3' => ! $loop->last])>
                    <div>
                        <div class="mb-0">
                            {{ $agreement->label() }}
                            <x-ui.stage-badge :tone="$tones[$agreement->status->value]" class="ms-2">{{ $agreement->status->label() }}</x-ui.stage-badge>
                        </div>
                        <div class="text-meta">
                            Closed {{ $agreement->closed_at?->format('j M Y') }}
                            @if ($agreement->amount_received_cents !== null)
                                · received {{ $rand($agreement->amount_received_cents) }}, fee {{ $rand($agreement->fee()) }}
                            @endif
                        </div>
                    </div>

                    @if ($agreement->signedCopy())
                        <a href="{{ route('fee-agreement.signed-copy', $agreement) }}" class="text-meta">Signed copy</a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif

<div class="card mb-3">
    <div class="card-body">
        <h3 class="card-title text-meta text-uppercase mb-3">Work recorded</h3>

        <div class="mb-0">{{ $rand($workValueCents) }}</div>
        <div class="text-meta">
            {{ $pricedCount }} billable {{ Str::plural('narration', $pricedCount) }} on the tariff{{ $matter->tariff_scale ? ', scale '.$matter->tariff_scale->value : '' }}.
            The value of the work done, not an amount anyone owes yet.
        </div>

        @if ($unpricedWork->isNotEmpty())
            <div class="alert alert-warning mt-3 mb-0">
                <p class="mb-1">{{ $unpricedWork->count() }} {{ Str::plural('narration', $unpricedWork->count()) }} could not be priced:</p>
                <ul class="mb-0">
                    @foreach ($unpricedWork->groupBy('reason') as $reason => $group)
                        <li>{{ $reason }}{{ $group->count() > 1 ? ' ('.$group->count().')' : '' }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h3 class="card-title text-meta text-uppercase mb-0">Disbursements</h3>
                @if ($disbursements->isNotEmpty())
                    <div class="text-meta">{{ $rand($disbursementsTotal) }} paid out</div>
                @endif
            </div>

            <x-ui.button variant="ghost-secondary" icon="plus" size="sm" aria-label="Add a disbursement"
                x-data x-on:click="$dispatch('sheet-open', 'new-disbursement')" />
        </div>

        @forelse ($disbursements as $disbursement)
            <div @class(['d-flex align-items-center justify-content-between gap-2', 'mb-3' => ! $loop->last])>
                <div>
                    <div class="mb-0">{{ $disbursement->description }}</div>
                    <div class="text-meta">
                        {{ $disbursement->incurred_on->format('j M Y') }} · {{ $disbursement->paid_from->label() }}
                        @if ($disbursement->receipt())
                            · <a href="{{ route('disbursement.receipt', $disbursement) }}">Receipt</a>
                        @endif
                    </div>
                </div>

                <div class="text-nowrap">
                    <span class="me-2">{{ $rand($disbursement->amount_cents) }}</span>
                    <form method="POST" action="{{ route('disbursement.destroy', $disbursement) }}" class="d-inline"
                        data-confirm="Remove the disbursement “{{ $disbursement->description }}”? Use this only for one recorded in error."
                        data-confirm-title="Remove disbursement" data-confirm-action="Remove" data-confirm-tone="danger" data-confirm-icon="trash">
                        @csrf
                        @method('DELETE')
                        <x-ui.button type="submit" variant="ghost-danger" size="sm">Remove</x-ui.button>
                    </form>
                </div>
            </div>
        @empty
            <p class="text-secondary mb-0">Nothing paid out on this matter yet.</p>
        @endforelse
    </div>
</div>

<x-ui.sheet name="new-fee-agreement" title="Record a fee agreement" lede="How the client agreed to be charged on this matter."
    bag="fee_agreement" :action="route('matter.fee-agreements.store', $matter)" enctype="multipart/form-data">
    <x-ui.select label="Agreement" name="kind" id="fee_kind" :error="$agreementErrors->first('kind')">
        @foreach ($feeKinds as $kind)
            <option value="{{ $kind->value }}" @selected(old('kind', $suggestedFeeKind->value) === $kind->value)>{{ $kind->label() }}</option>
        @endforeach
    </x-ui.select>

    <x-ui.input label="Share (%)" name="percent" id="fee_percent" type="number" step="0.01" min="0" max="100" :value="old('percent')"
        hint="Leave empty for the firm's default: contingency {{ $percent($defaultPercents['contingency']) }}%, estate {{ $percent($defaultPercents['estate']) }}%. A mandate has no share."
        :error="$agreementErrors->first('percent')" />

    <x-ui.input label="Signed on" name="signed_on" id="fee_signed_on" type="date" :value="old('signed_on')"
        :error="$agreementErrors->first('signed_on')" />

    <x-ui.input label="Signed copy" name="signed_copy" id="fee_signed_copy" type="file"
        :error="$agreementErrors->first('signed_copy')" />

    <x-slot:footer>
        <div class="d-flex align-items-center justify-content-between">
            <x-ui.button variant="outline-secondary" size="sm" x-on:click="close()">Cancel</x-ui.button>
            <x-ui.button type="submit" variant="primary" size="sm">Record agreement</x-ui.button>
        </div>
    </x-slot:footer>
</x-ui.sheet>

@if ($activeAgreement?->kind->isPercentage())
    <x-ui.sheet name="finalise-fee-agreement" title="Finalise the agreement"
        lede="{{ $activeAgreement->kind === \App\Enums\Billing\FeeAgreementKind::Estate ? 'The estate funds after administration and distribution.' : 'What the client received when the matter was finalised.' }}"
        bag="fee_agreement_finalise" :action="route('fee-agreement.finalise', $activeAgreement)">
        <x-ui.input label="Amount received (R)" name="amount_received" id="fee_amount_received" type="number" step="0.01" min="0"
            :value="old('amount_received')" hint="The firm's fee is {{ $percent($activeAgreement->percent()) }}% of this."
            :error="$errors->getBag('fee_agreement_finalise')->first('amount_received')" />

        <x-slot:footer>
            <div class="d-flex align-items-center justify-content-between">
                <x-ui.button variant="outline-secondary" size="sm" x-on:click="close()">Cancel</x-ui.button>
                <x-ui.button type="submit" variant="primary" size="sm">Finalise</x-ui.button>
            </div>
        </x-slot:footer>
    </x-ui.sheet>
@endif

<x-ui.sheet name="new-disbursement" title="Add a disbursement" lede="Money paid out on this matter for the client, recovered at cost on a bill."
    bag="disbursement" :action="route('matter.disbursements.store', $matter)" enctype="multipart/form-data">
    <x-ui.input label="Paid on" name="incurred_on" id="disbursement_incurred_on" type="date" :value="old('incurred_on', now()->toDateString())"
        :error="$errors->getBag('disbursement')->first('incurred_on')" />

    <x-ui.input label="Description" name="description" id="disbursement_description" :value="old('description')"
        hint="As it should read on the bill, e.g. Sheriff's fee for service of summons"
        :error="$errors->getBag('disbursement')->first('description')" />

    <x-ui.input label="Amount (R)" name="amount" id="disbursement_amount" type="number" step="0.01" min="0.01" :value="old('amount')"
        :error="$errors->getBag('disbursement')->first('amount')" />

    <x-ui.select label="Paid from" name="paid_from" id="disbursement_paid_from" :error="$errors->getBag('disbursement')->first('paid_from')">
        @foreach ($firmAccounts as $account)
            <option value="{{ $account->value }}" @selected(old('paid_from', 'business') === $account->value)>{{ $account->label() }}</option>
        @endforeach
    </x-ui.select>

    <x-ui.input label="Receipt" name="receipt" id="disbursement_receipt" type="file"
        :error="$errors->getBag('disbursement')->first('receipt')" />

    <x-slot:footer>
        <div class="d-flex align-items-center justify-content-between">
            <x-ui.button variant="outline-secondary" size="sm" x-on:click="close()">Cancel</x-ui.button>
            <x-ui.button type="submit" variant="primary" size="sm">Add disbursement</x-ui.button>
        </div>
    </x-slot:footer>
</x-ui.sheet>
