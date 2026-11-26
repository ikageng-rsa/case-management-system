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

            @can('manageFees', $matter)
                @unless ($activeAgreement)
                    <x-ui.button variant="primary" icon="plus" size="sm"
                        x-data x-on:click="$dispatch('sheet-open', 'new-fee-agreement')">Record fee agreement</x-ui.button>
                @endunless
            @endcan
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

                @can('manageFees', $matter)
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
                @endcan
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
                            @can('viewBills', $matter)
                                @if ($agreement->amount_received_cents !== null)
                                    · received {{ $rand($agreement->amount_received_cents) }}, fee {{ $rand($agreement->fee()) }}
                                @endif
                            @endcan
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-3 text-nowrap">
                        @if ($agreement->signedCopy())
                            <a href="{{ route('fee-agreement.signed-copy', $agreement) }}" class="text-meta">Signed copy</a>
                        @endif

                        @can('viewBills', $matter)
                            @if ($feeBills->has($agreement->getKey()))
                                <a href="{{ route('bill.show', $feeBills[$agreement->getKey()]) }}" class="text-meta">Fee bill {{ $feeBills[$agreement->getKey()]->number ?? '(draft)' }}</a>
                            @elseif ($agreement->isFinalised() && $agreement->fee())
                                @can('draftBills', $matter)
                                    <x-ui.button variant="outline-secondary" size="sm"
                                        x-data x-on:click="$dispatch('sheet-open', 'fee-bill-{{ $agreement->getKey() }}')">Raise fee bill</x-ui.button>
                                @endcan
                            @endif
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif

@php
    $billTones = ['draft' => 'accent', 'issued' => 'success', 'void' => 'neutral'];
    $paymentTones = ['unpaid' => 'neutral', 'part_paid' => 'accent', 'settled' => 'success'];
@endphp

@can('viewBills', $matter)
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h3 class="card-title text-meta text-uppercase mb-0">Bills</h3>
                @if ($owedByClientCents > 0 || $owedByOtherSideCents > 0)
                    <div class="text-meta">
                        {{ collect([
                            $owedByClientCents > 0 ? $rand($owedByClientCents).' owed by the client' : null,
                            $owedByOtherSideCents > 0 ? $rand($owedByOtherSideCents).' owed on bills of cost' : null,
                        ])->filter()->implode(' · ') }}
                    </div>
                @endif
            </div>

            @can('draftBills', $matter)
                <x-ui.button variant="primary" icon="plus" size="sm"
                    x-data x-on:click="$dispatch('sheet-open', 'new-bill')">Raise a bill</x-ui.button>
            @endcan
        </div>

        @forelse ($bills as $bill)
            <div @class(['d-flex align-items-center justify-content-between gap-2', 'mb-3' => ! $loop->last])>
                <div>
                    <div class="mb-0">
                        @can('viewBills', $matter)
                            <a href="{{ route('bill.show', $bill) }}">{{ $bill->number ?? 'Draft' }}</a>
                        @else
                            {{ $bill->number ?? 'Draft' }}
                        @endcan
                        <x-ui.stage-badge :tone="$billTones[$bill->status->value]" class="ms-2">{{ $bill->status->label() }}</x-ui.stage-badge>
                        @if ($bill->paymentState())
                            <x-ui.stage-badge :tone="$paymentTones[$bill->paymentState()->value]" class="ms-1">{{ $bill->paymentState()->label() }}</x-ui.stage-badge>
                        @endif
                    </div>
                    <div class="text-meta">{{ $bill->kind->label() }} · {{ $bill->addressee }} · {{ ($bill->issued_at ?? $bill->created_at)->format('j M Y') }}</div>
                </div>
                <div class="text-end text-nowrap">
                    <div>{{ $rand($bill->total_cents) }}</div>
                    @if ($bill->paymentState() && $bill->paid_cents > 0 && $bill->balanceCents() > 0)
                        <div class="text-meta">{{ $rand($bill->balanceCents()) }} still owed</div>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-secondary mb-0">No bills on this matter yet.</p>
        @endforelse
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <h3 class="card-title text-meta text-uppercase mb-3">Work recorded</h3>

        <div class="mb-0">{{ $rand($workValueCents) }}</div>
        <div class="text-meta">
            {{ $pricedCount }} billable {{ Str::plural('narration', $pricedCount) }} on the tariff{{ $matter->tariff_scale ? ', scale '.$matter->tariff_scale->value : '' }}.
            The value of the work done, not an amount anyone owes yet.
        </div>

        @if ($timeOnTopMinutes > 0)
            <div class="text-meta mt-2">
                Plus {{ intdiv($timeOnTopMinutes, 60) > 0 ? intdiv($timeOnTopMinutes, 60).' h ' : '' }}{{ $timeOnTopMinutes % 60 > 0 ? ($timeOnTopMinutes % 60).' min ' : '' }}of time on top, rated when the bill is raised.
            </div>
        @endif

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
@endcan

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h3 class="card-title text-meta text-uppercase mb-0">Disbursements</h3>
                @if ($disbursements->isNotEmpty())
                    <div class="text-meta">{{ $rand($disbursementsTotal) }} paid out</div>
                @endif
            </div>

            @can('manageFees', $matter)
                <x-ui.button variant="ghost-secondary" icon="plus" size="sm" aria-label="Add a disbursement"
                    x-data x-on:click="$dispatch('sheet-open', 'new-disbursement')" />
            @endcan
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
@can('manageFees', $matter)
                    <form method="POST" action="{{ route('disbursement.destroy', $disbursement) }}" class="d-inline"
                        data-confirm="Remove the disbursement “{{ $disbursement->description }}”? Use this only for one recorded in error."
                        data-confirm-title="Remove disbursement" data-confirm-action="Remove" data-confirm-tone="danger" data-confirm-icon="trash">
                        @csrf
                        @method('DELETE')
                        <x-ui.button type="submit" variant="ghost-danger" size="sm">Remove</x-ui.button>
                    </form>
                    @endcan
                </div>
            </div>
        @empty
            <p class="text-secondary mb-0">Nothing paid out on this matter yet.</p>
        @endforelse
    </div>
</div>

<x-ui.sheet name="new-fee-agreement" title="Record a fee agreement" lede="How the client agreed to be charged on this matter."
    bag="fee_agreement" :action="route('matter.fee-agreements.store', $matter)" enctype="multipart/form-data"
    :form-data="'{ kind: '.\Illuminate\Support\Js::from(old('kind', $suggestedFeeKind->value)).' }'">
    <x-ui.select label="Agreement" name="kind" id="fee_kind" x-model="kind" :error="$agreementErrors->first('kind')">
        @foreach ($feeKinds as $kind)
            <option value="{{ $kind->value }}" @selected(old('kind', $suggestedFeeKind->value) === $kind->value)>{{ $kind->label() }}</option>
        @endforeach
    </x-ui.select>

    {{-- A mandate has no share, so the field shows, and is sent, only for an agreement that takes one. --}}
    <div x-show="kind !== 'mandate'" x-cloak>
        <x-ui.input label="Share (%)" name="percent" id="fee_percent" type="number" step="0.01" min="0" max="100" :value="old('percent')"
            x-bind:disabled="kind === 'mandate'" :error="$agreementErrors->first('percent')" />

        @unless ($agreementErrors->has('percent'))
            @foreach ($defaultPercents as $feeKind => $default)
                <div class="form-hint mt-n2 mb-3" x-show="kind === '{{ $feeKind }}'" x-cloak>Leave empty for the firm's default of {{ $percent($default) }}%.</div>
            @endforeach
        @endunless
    </div>

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

@if (auth()->user()->can('draftBills', $matter) && auth()->user()->can('viewBills', $matter))
    @php
        $billErrors = $errors->getBag('bill');
        $billKind = old('kind', $billKinds[0]->value);
        // A statement starts on the client's address on file, when the person may see it; a form sent back keeps what was typed.
        $billForm = [
            'kind' => $billKind,
            'clientAddress' => (string) $clientAddress,
            'address' => (string) ($billErrors->any() ? old('address') : ($billKind === 'statement' ? $clientAddress : '')),
        ];
        // Changing the bill swaps the client's address in or out, unless another address has been typed.
        $billFormData = '{ ...'.\Illuminate\Support\Js::from($billForm).', init() { this.$watch("kind", kind => {'
            .' if (kind === "statement" && this.address === "") this.address = this.clientAddress;'
            .' if (kind !== "statement" && this.address === this.clientAddress) this.address = "";'
            .' }) } }';
    @endphp

    <x-ui.sheet name="new-bill" title="Raise a bill" lede="Drawn as a draft from the work and disbursements not yet billed, to check before it is issued."
        bag="bill" :action="route('matter.bills.store', $matter)"
        :form-data="$billFormData">
        @if ($billErrors->has('bill'))
            <div class="alert alert-danger">
                <p class="mb-1">Nothing was drawn:</p>
                <ul class="mb-0">
                    @foreach ($billErrors->get('bill') as $problem)
                        <li>{{ $problem }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-ui.select label="Bill" name="kind" id="bill_kind" x-model="kind" :error="$billErrors->first('kind')">
            @foreach ($billKinds as $kind)
                <option value="{{ $kind->value }}" @selected(old('kind') === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-ui.select>

        <div>
            <x-ui.input label="Addressed to" name="addressee" id="bill_addressee" list="bill_addressees"
                :value="old('addressee', $addressees->first())"
                :error="$billErrors->first('addressee')" />

            @unless ($billErrors->has('addressee'))
                <div class="form-hint mt-n2 mb-3" x-show="kind === 'statement'">The client.</div>
                <div class="form-hint mt-n2 mb-3" x-show="kind === 'bill_of_cost'" x-cloak>The other side, such as the Minister of Police.</div>
            @endunless
        </div>
        <div class="mb-3">
            <label class="form-label form-label-mono" for="bill_address">Address</label>
            <textarea class="form-control @error('address', 'bill') is-invalid @enderror" id="bill_address" name="address" rows="3" x-model="address"></textarea>
            @error('address', 'bill')
                <div class="invalid-feedback">{{ $message }}</div>
            @else
                <div class="form-hint" x-show="kind === 'statement'">One line per line. {{ $clientAddress ? "Starts on the client's address on file." : "Leave empty to use the client's postal address on file." }}</div>
                <div class="form-hint" x-show="kind === 'bill_of_cost'" x-cloak>One line per line.</div>
            @enderror
        </div>

        <datalist id="bill_addressees">
            @foreach ($addressees as $addressee)
                <option value="{{ $addressee }}"></option>
            @endforeach
        </datalist>

        <x-ui.select label="Tariff scale" name="scale" id="bill_scale" :error="$billErrors->first('scale')"
            hint="Starts on the matter's scale; change it for this bill only.">
            <option value="">{{ $matter->tariff_scale ? 'Scale '.$matter->tariff_scale->value.' (the matter\'s)' : 'Choose…' }}</option>
            @foreach ($scales as $scale)
                <option value="{{ $scale->value }}" @selected(old('scale') === $scale->value)>Scale {{ $scale->value }}</option>
            @endforeach
        </x-ui.select>

        <div class="row g-2">
            <div class="col-6">
                <x-ui.input label="Work from" name="from" id="bill_from" type="date" :value="old('from')" :error="$billErrors->first('from')" />
            </div>
            <div class="col-6">
                <x-ui.input label="Up to" name="to" id="bill_to" type="date" :value="old('to')" :error="$billErrors->first('to')" />
            </div>
        </div>
        <p class="form-hint mt-n2">Leave both empty for all work not yet billed.</p>

        @if ($timeOnTop->isNotEmpty())
            <h4 class="text-meta text-uppercase mt-3 mb-2">Time on top</h4>
            <p class="form-hint">The hourly rate to charge each person's time at on this bill.</p>

            @foreach ($timeOnTop as $person)
                <x-ui.input :label="$person['user']->name.' ('.intdiv($person['minutes'], 60).' h '.($person['minutes'] % 60).' min) — R an hour'"
                    name="time_rates[{{ $person['user']->getKey() }}]" id="bill_rate_{{ $loop->index }}" type="number" step="0.01" min="0"
                    :value="old('time_rates.'.$person['user']->getKey())"
                    :error="$billErrors->first('time_rates.'.$person['user']->getKey())" />
            @endforeach
        @endif

        <x-slot:footer>
            <div class="d-flex align-items-center justify-content-between">
                <x-ui.button variant="outline-secondary" size="sm" x-on:click="close()">Cancel</x-ui.button>
                <x-ui.button type="submit" variant="primary" size="sm">Draw the bill</x-ui.button>
            </div>
        </x-slot:footer>
    </x-ui.sheet>

    @foreach ($feeAgreements->filter(fn ($agreement) => $agreement->isFinalised() && $agreement->fee() && ! $feeBills->has($agreement->getKey())) as $agreement)
        @php
            $feeBillBag = \App\Http\Requests\Billing\DraftFeeBillRequest::bagFor($agreement->getKey());
            $feeBillErrors = $errors->getBag($feeBillBag);
            $feeBillOld = fn (string $field, ?string $default = null) => $feeBillErrors->any() ? old($field, $default) : $default;
        @endphp

        <x-ui.sheet name="fee-bill-{{ $agreement->getKey() }}" title="Raise a fee bill"
            lede="Drawn as a draft with one line, the firm's agreed share, to check before it is issued."
            :bag="$feeBillBag" :action="route('fee-agreement.bill', $agreement)">
            @if ($feeBillErrors->has('fee_bill'))
                <div class="alert alert-danger">
                    <p class="mb-1">Nothing was drawn:</p>
                    <ul class="mb-0">
                        @foreach ($feeBillErrors->get('fee_bill') as $problem)
                            <li>{{ $problem }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mb-3">
                <div class="mb-0">{{ $rand($agreement->fee()) }}</div>
                <div class="text-meta">{{ $percent($agreement->percent()) }}% of the {{ $rand($agreement->amount_received_cents) }} received, under the {{ strtolower($agreement->kind->label()) }} agreement</div>
            </div>

            <x-ui.input label="Addressed to" name="addressee" id="fee_bill_addressee_{{ $agreement->getKey() }}"
                :value="$feeBillOld('addressee', $matter->client->full_name)"
                :error="$feeBillErrors->first('addressee')" />

            <div class="mb-3">
                <label class="form-label form-label-mono" for="fee_bill_address_{{ $agreement->getKey() }}">Address</label>
                <textarea @class(['form-control', 'is-invalid' => $feeBillErrors->has('address')]) id="fee_bill_address_{{ $agreement->getKey() }}" name="address" rows="3">{{ $feeBillOld('address', $clientAddress) }}</textarea>
                @if ($feeBillErrors->has('address'))
                    <div class="invalid-feedback">{{ $feeBillErrors->first('address') }}</div>
                @else
                    <div class="form-hint">One line per line. {{ $clientAddress ? "Starts on the client's address on file." : "Leave empty to use the client's postal address on file." }}</div>
                @endif
            </div>

            <x-slot:footer>
                <div class="d-flex align-items-center justify-content-between">
                    <x-ui.button variant="outline-secondary" size="sm" x-on:click="close()">Cancel</x-ui.button>
                    <x-ui.button type="submit" variant="primary" size="sm">Draw the fee bill</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.sheet>
    @endforeach
@endif
