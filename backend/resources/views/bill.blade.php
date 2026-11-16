@extends('layouts.app')

@section('title', ($bill->number ?? 'Draft bill').'')

@section('content')
    @php
        $rand = fn (int $cents) => 'R '.number_format($cents / 100, 2, '.', ' ');
        $editable = $bill->isDraft() && auth()->user()->can('draftBills', $bill->matter);
        $isFeeBill = $bill->kind === \App\Enums\Billing\BillKind::AgreementFee;
        $percent = rtrim(rtrim(number_format($bill->drawing_fee_percent_hundredths / 100, 2, '.', ''), '0'), '.');
        $tones = ['draft' => 'accent', 'issued' => 'success', 'void' => 'neutral'];
        $paymentTones = ['unpaid' => 'neutral', 'part_paid' => 'accent', 'settled' => 'success'];
        $paymentState = $bill->paymentState();
        $canRecordPayments = auth()->user()->can('recordPayments', $bill->matter);
        $units = fn ($line) => rtrim(rtrim((string) $line->units, '0'), '.');
    @endphp

    <div class="mb-3 d-flex align-items-start justify-content-between gap-3">
        <div>
            {{-- Opened from the debtors report, the way back is to the report; otherwise to the matter the bill is on. --}}
            @if (request('from') === 'debtors' && auth()->user()->can('view_billing_reports'))
                <a href="{{ route('reports', ['tab' => 'debtors']) }}" class="text-meta">← Debtors report</a>
                <span class="text-meta">·</span>
                <a href="{{ route('matter.show', [$bill->matter, 'tab' => 'billing']) }}" class="text-meta">{{ $bill->matter->reference }}</a>
            @else
                <a href="{{ route('matter.show', [$bill->matter, 'tab' => 'billing']) }}" class="text-meta">← {{ $bill->matter->reference }}</a>
            @endif
            <h1 class="mb-1">
                {{ $bill->kind->label() }} {{ $bill->number }}
                <x-ui.stage-badge :tone="$tones[$bill->status->value]" class="ms-2">{{ $bill->status->label() }}</x-ui.stage-badge>
                @if ($paymentState)
                    <x-ui.stage-badge :tone="$paymentTones[$paymentState->value]" class="ms-1">{{ $paymentState->label() }}</x-ui.stage-badge>
                @endif
            </h1>
            <p class="text-secondary mb-0">
                To {{ $bill->addressee }}{{ $bill->addressee_address ? ', '.Str::of($bill->addressee_address)->replaceMatches('/\R+/', ', ') : '' }}
                @if ($bill->scale) · scale {{ $bill->scale->value }} @endif
                @if ($bill->period_from || $bill->period_to)
                    · work {{ $bill->period_from ? 'from '.$bill->period_from->format('j M Y') : '' }} {{ $bill->period_to ? 'up to '.$bill->period_to->format('j M Y') : '' }}
                @endif
                · drawn {{ $bill->created_at->format('j M Y') }}{{ $bill->draftedBy ? ' by '.$bill->draftedBy->name : '' }}
                @if ($bill->issued_at) · issued {{ $bill->issued_at->format('j M Y') }}{{ $bill->issuedBy ? ' by '.$bill->issuedBy->name : '' }} @endif
            </p>
        </div>

        <div class="d-flex gap-2">
            <x-ui.button :href="route('bill.pdf', $bill)" variant="outline-secondary" icon="download" size="sm">PDF</x-ui.button>

            @if ($bill->isDraft())
                @can('draftBills', $bill->matter)
                    @unless ($isFeeBill)
                        <x-ui.button variant="outline-secondary" icon="plus" size="sm"
                            x-data x-on:click="$dispatch('sheet-open', 'add-work')">Add work</x-ui.button>
                    @endunless

                    <form method="POST" action="{{ route('bill.destroy', $bill) }}"
                        data-confirm="{{ $isFeeBill ? 'Delete this draft? The fee can be billed again from the agreement.' : 'Delete this draft? The work and disbursements on it become free to bill again.' }}"
                        data-confirm-title="Delete draft" data-confirm-action="Delete" data-confirm-tone="danger" data-confirm-icon="trash">
                        @csrf
                        @method('DELETE')
                        <x-ui.button type="submit" variant="outline-danger" size="sm">Delete draft</x-ui.button>
                    </form>
                @endcan

                @can('issueBills', $bill->matter)
                    <form method="POST" action="{{ route('bill.issue', $bill) }}"
                        data-confirm="Issue this bill to {{ $bill->addressee }} for {{ $rand($bill->total_cents) }}? It is numbered and locked, and from then on it is owed."
                        data-confirm-title="Issue bill" data-confirm-action="Issue" data-confirm-icon="check">
                        @csrf
                        <x-ui.button type="submit" variant="primary" icon="check" size="sm">Issue</x-ui.button>
                    </form>
                @endcan
            @endif

            @if ($bill->isIssued())
                @if ($canRecordPayments && $bill->balanceCents() > 0)
                    <x-ui.button variant="primary" icon="plus" size="sm"
                        x-data x-on:click="$dispatch('sheet-open', 'record-payment')">Record payment</x-ui.button>
                @endif

                @can('voidBills', $bill->matter)
                    <x-ui.button variant="outline-danger" size="sm"
                        x-data x-on:click="$dispatch('sheet-open', 'void-bill')">Void</x-ui.button>
                @endcan
            @endif
        </div>
    </div>

    @if ($bill->isVoid())
        <div class="alert alert-secondary">
            Voided {{ $bill->voided_at->format('j M Y') }}{{ $bill->voidedBy ? ' by '.$bill->voidedBy->name : '' }}: {{ $bill->void_reason }}.
            Nothing is owed on it, and its work can be billed again.
        </div>
    @endif

    @if ($errors->getBag('bill')->any())
        <div class="alert alert-danger">{{ $errors->getBag('bill')->first() }}</div>
    @endif

    @if ($bill->isDraft())
        <div class="alert alert-info">This bill is a draft. Nothing is owed on it until it is issued.</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Item</th>
                        <th>Description</th>
                        <th class="text-end">Fees</th>
                        <th class="text-end">Disbursements</th>
                        @if ($editable)
                            <th></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($bill->lines as $line)
                        <tr>
                            <td class="text-meta text-nowrap">{{ $line->occurred_on->format('j M Y') }}</td>
                            <td class="text-meta">{{ $line->item ?? '—' }}</td>
                            <td>
                                {{ $line->description }}
                                @if ($line->kind === \App\Enums\Billing\BillLineKind::TimeOnTop)
                                    <div class="text-meta">{{ $units($line) }} h at {{ $rand($line->rate_cents) }} an hour · {{ $line->user?->name }}</div>
                                @elseif ($line->kind === \App\Enums\Billing\BillLineKind::Work)
                                    <div class="text-meta">{{ $units($line) }} × {{ $rand($line->rate_cents) }} · {{ $line->user?->name }}</div>
                                @endif
                                @if ($line->original_amount_cents !== null)
                                    <div class="text-meta">Changed from {{ $rand($line->original_amount_cents) }}{{ $line->overriddenBy ? ' by '.$line->overriddenBy->name : '' }}</div>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">{{ $line->kind->isFee() ? $rand($line->fee_cents) : '' }}</td>
                            <td class="text-end text-nowrap">{{ $line->kind->isFee() ? '' : $rand($line->disbursement_cents) }}</td>
                            @if ($editable)
                                <td class="text-end text-nowrap">
                                    <x-ui.button variant="ghost-secondary" size="sm" icon="pencil" aria-label="Change the amount" title="Change the amount"
                                        x-data x-on:click="$dispatch('sheet-open', 'edit-line-{{ $line->getKey() }}')" />
                                    @unless ($isFeeBill)
                                        <form method="POST" action="{{ route('bill.lines.destroy', [$bill, $line]) }}" class="d-inline"
                                            data-confirm="Take “{{ Str::limit($line->description, 60) }}” off this draft? Its work stays unbilled for a later bill."
                                            data-confirm-title="Remove line" data-confirm-action="Remove" data-confirm-tone="danger" data-confirm-icon="trash">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.button type="submit" variant="ghost-danger" size="sm" icon="trash" aria-label="Remove the line" title="Remove the line" />
                                        </form>
                                    @endunless
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-end">Fees</td>
                        <td class="text-end text-nowrap">{{ $rand($bill->fees_cents) }}</td>
                        <td></td>
                        @if ($editable) <td></td> @endif
                    </tr>
                    @unless ($isFeeBill)
                        <tr>
                            <td colspan="3" class="text-end">Drawing fee ({{ $percent }}%)</td>
                            <td class="text-end text-nowrap">{{ $rand($bill->drawing_fee_cents) }}</td>
                            <td></td>
                            @if ($editable) <td></td> @endif
                        </tr>
                    @endunless
                    <tr>
                        <td colspan="3" class="text-end">Disbursements</td>
                        <td></td>
                        <td class="text-end text-nowrap">{{ $rand($bill->disbursements_cents) }}</td>
                        @if ($editable) <td></td> @endif
                    </tr>
                    <tr>
                        <td colspan="3" class="text-end"><strong>Total due</strong></td>
                        <td colspan="2" class="text-end text-nowrap"><strong>{{ $rand($bill->total_cents) }}</strong></td>
                        @if ($editable) <td></td> @endif
                    </tr>
                    @if ($paymentState && $bill->paid_cents > 0)
                        <tr>
                            <td colspan="3" class="text-end">Paid</td>
                            <td colspan="2" class="text-end text-nowrap">{{ $rand($bill->paid_cents) }}</td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-end"><strong>Balance</strong></td>
                            <td colspan="2" class="text-end text-nowrap"><strong>{{ $rand($bill->balanceCents()) }}</strong></td>
                        </tr>
                    @endif
                </tfoot>
            </table>
        </div>
    </div>

    @if ($bill->payments->isNotEmpty())
        <div class="card mt-3">
            <div class="card-body">
                <h3 class="card-title text-meta text-uppercase mb-3">Payments</h3>

                @foreach ($bill->payments as $payment)
                    <div @class(['d-flex align-items-center justify-content-between gap-2', 'mb-3' => ! $loop->last])>
                        <div>
                            <div class="mb-0">
                                {{ $payment->received_on->format('j M Y') }} · {{ $payment->method->label() }} into the {{ strtolower($payment->account->label()) }}
                                @if ($payment->isReversed())
                                    <x-ui.stage-badge tone="neutral" class="ms-2">Reversed</x-ui.stage-badge>
                                @endif
                            </div>
                            <div class="text-meta">
                                @if ($payment->reference) Ref {{ $payment->reference }} · @endif
                                recorded {{ $payment->created_at->format('j M Y') }}{{ $payment->recordedBy ? ' by '.$payment->recordedBy->name : '' }}
                                @if ($payment->proof())
                                    · <a href="{{ route('payment.proof', $payment) }}">Proof of payment</a>
                                @endif
                            </div>
                            @if ($payment->isReversed())
                                <div class="text-meta">Reversed {{ $payment->reversed_at->format('j M Y') }}{{ $payment->reversedBy ? ' by '.$payment->reversedBy->name : '' }}: {{ $payment->reverse_reason }}</div>
                            @endif
                        </div>

                        <div class="text-nowrap">
                            <span @class(['me-2', 'text-decoration-line-through text-secondary' => $payment->isReversed()])>{{ $rand($payment->amount_cents) }}</span>
                            @if ($canRecordPayments && ! $payment->isReversed())
                                <x-ui.button variant="ghost-danger" size="sm"
                                    x-data x-on:click="$dispatch('sheet-open', 'reverse-payment-{{ $payment->getKey() }}')">Reverse</x-ui.button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($canRecordPayments)
        @if ($bill->isIssued() && $bill->balanceCents() > 0)
            @php
                $paymentErrors = $errors->getBag('payment');
            @endphp

            <x-ui.sheet name="record-payment" title="Record a payment" lede="Money received on {{ $bill->number }}. {{ $rand($bill->balanceCents()) }} is still owed."
                bag="payment" :action="route('bill.payments.store', $bill)" enctype="multipart/form-data">
                <x-ui.input label="Amount (R)" name="amount" id="payment_amount" type="number" step="0.01" min="0.01"
                    :value="old('amount', number_format($bill->balanceCents() / 100, 2, '.', ''))"
                    hint="Starts on what is still owed. Change it for a part payment."
                    :error="$paymentErrors->first('amount')" />

                <x-ui.input label="Received on" name="received_on" id="payment_received_on" type="date" :value="old('received_on', now()->toDateString())"
                    :error="$paymentErrors->first('received_on')" />

                <x-ui.select label="Paid by" name="method" id="payment_method" :error="$paymentErrors->first('method')">
                    @foreach ($paymentMethods as $method)
                        <option value="{{ $method->value }}" @selected(old('method', 'eft') === $method->value)>{{ $method->label() }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select label="Paid into" name="account" id="payment_account" :error="$paymentErrors->first('account')">
                    @foreach ($firmAccounts as $account)
                        <option value="{{ $account->value }}" @selected(old('account', 'business') === $account->value)>{{ $account->label() }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.input label="Reference" name="reference" id="payment_reference" :value="old('reference')"
                    hint="As it shows on the bank statement or slip, if there is one."
                    :error="$paymentErrors->first('reference')" />

                <x-ui.input label="Proof of payment" name="proof" id="payment_proof" type="file"
                    hint="PDF or image, up to 5 MB."
                    :error="$paymentErrors->first('proof')" />

                @unless ($bill->kind === \App\Enums\Billing\BillKind::BillOfCost)
                    <div class="form-check">
                        <input type="hidden" name="email_client" value="0">
                        <input class="form-check-input" type="checkbox" id="payment_email_client" name="email_client" value="1"
                            @checked($paymentErrors->any() ? old('email_client') : true)>
                        <label class="form-check-label" for="payment_email_client">Email the client</label>
                        <div class="form-hint">Tells them the amount received and what is still owed, at their email address on file.</div>
                    </div>
                @endunless

                <x-slot:footer>
                    <div class="d-flex align-items-center justify-content-between">
                        <x-ui.button variant="outline-secondary" size="sm" x-on:click="close()">Cancel</x-ui.button>
                        <x-ui.button type="submit" variant="primary" size="sm">Record payment</x-ui.button>
                    </div>
                </x-slot:footer>
            </x-ui.sheet>
        @endif

        @foreach ($bill->payments->reject->isReversed() as $payment)
            @php
                $reverseBag = \App\Http\Requests\Billing\ReversePaymentRequest::bagFor($payment->getKey());
                $reverseErrors = $errors->getBag($reverseBag);
            @endphp

            <x-ui.sheet name="reverse-payment-{{ $payment->getKey() }}" title="Reverse the payment" icon="x"
                lede="{{ $rand($payment->amount_cents) }} received {{ $payment->received_on->format('j M Y') }}. For a payment recorded in error. It stays on record with the reason and no longer counts towards the bill."
                :bag="$reverseBag" :action="route('payment.reverse', $payment)">
                <div class="mb-3">
                    <label class="form-label form-label-mono" for="reverse_reason_{{ $payment->getKey() }}">Reason</label>
                    <textarea @class(['form-control', 'is-invalid' => $reverseErrors->has('reason')]) id="reverse_reason_{{ $payment->getKey() }}" name="reason" rows="3">{{ $reverseErrors->any() ? old('reason') : '' }}</textarea>
                    @if ($reverseErrors->has('reason'))
                        <div class="invalid-feedback">{{ $reverseErrors->first('reason') }}</div>
                    @endif
                </div>

                <x-slot:footer>
                    <div class="d-flex align-items-center justify-content-between">
                        <x-ui.button variant="outline-secondary" size="sm" x-on:click="close()">Cancel</x-ui.button>
                        <x-ui.button type="submit" variant="danger" size="sm">Reverse the payment</x-ui.button>
                    </div>
                </x-slot:footer>
            </x-ui.sheet>
        @endforeach
    @endif

    @if ($editable)
        @foreach ($bill->lines as $line)
            @php
                $bag = 'bill_line_'.$line->getKey();
            @endphp
            <x-ui.sheet name="edit-line-{{ $line->getKey() }}" title="Change the amount" :lede="Str::limit($line->description, 120)"
                :bag="$bag" :action="route('bill.lines.update', [$bill, $line])" method="PATCH">
                <x-ui.input label="Amount (R)" name="amount" id="line_amount_{{ $line->getKey() }}" type="number" step="0.01" min="0"
                    :value="$errors->getBag($bag)->any() ? old('amount') : number_format($line->amount() / 100, 2, '.', '')"
                    :hint="'Drawn at '.$rand($line->original_amount_cents ?? $line->amount()).'. The change is kept with your name.'"
                    :error="$errors->getBag($bag)->first('amount')" />

                <x-slot:footer>
                    <div class="d-flex align-items-center justify-content-between">
                        <x-ui.button variant="outline-secondary" size="sm" x-on:click="close()">Cancel</x-ui.button>
                        <x-ui.button type="submit" variant="primary" size="sm">Save amount</x-ui.button>
                    </div>
                </x-slot:footer>
            </x-ui.sheet>
        @endforeach
    @endif

    @if ($editable && ! $isFeeBill)
        @php
            $addErrors = $errors->getBag('bill_add');
            $ratesOnBill = $bill->lines->where('kind', \App\Enums\Billing\BillLineKind::TimeOnTop)->pluck('rate_cents', 'user_id');
            $timePeople = $unbilled['narrations']->filter(fn ($narration) => $narration->minutes > 0)->groupBy('author_id');
            $searchText = fn (string ...$parts) => Str::lower(implode(' ', $parts));
        @endphp

        <x-ui.sheet name="add-work" title="Add work" lede="Work and disbursements recorded on the matter that are not yet on a {{ Str::lower($bill->kind->label()) }}."
            bag="bill_add" :action="route('bill.lines.store', $bill)">
            @if ($addErrors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($addErrors->all() as $problem)
                            <li>{{ $problem }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($unbilled['narrations']->isEmpty() && $unbilled['disbursements']->isEmpty())
                <p class="text-secondary mb-0">Everything recorded on this matter is already on a {{ Str::lower($bill->kind->label()) }}.</p>
            @else
                <div x-data="{ q: '', picked: 0 }" x-on:change="picked = $el.querySelectorAll('input[data-pick]:checked').length">
                    <x-ui.search-field placeholder="Search by date, work, description or person" :shortcut="null" wrapper="mb-2"
                        x-model="q" aria-label="Search the unbilled work" />
                    <div class="text-meta mb-2"><span x-text="picked"></span> chosen</div>

                    <div class="border rounded" style="max-height: 50vh; overflow-y: auto;">
                        @foreach ($unbilled['narrations'] as $narration)
                            @php
                                $search = $searchText($narration->occurred_at->format('j M Y'), $narration->activityType->name, $narration->activityType->code, $narration->body, $narration->author->name);
                            @endphp
                            <label class="d-flex gap-2 p-2 border-bottom" data-search="{{ $search }}" x-show="! q || $el.dataset.search.includes(q.toLowerCase())">
                                <input type="checkbox" class="form-check-input mt-1" name="narrations[]" value="{{ $narration->getKey() }}" data-pick
                                    @checked(in_array($narration->getKey(), old('narrations', []), true))>
                                <span>
                                    <span class="d-block">{{ $narration->body }}</span>
                                    <span class="text-meta">
                                        {{ $narration->occurred_at->format('j M Y') }} · {{ $narration->activityType->name }} · {{ $narration->author->name }}
                                        @if ($narration->minutes) · + {{ $narration->minutes }} min @endif
                                    </span>
                                </span>
                            </label>
                        @endforeach

                        @foreach ($unbilled['disbursements'] as $disbursement)
                            @php
                                $search = $searchText($disbursement->incurred_on->format('j M Y'), 'disbursement', $disbursement->description);
                            @endphp
                            <label class="d-flex gap-2 p-2 border-bottom" data-search="{{ $search }}" x-show="! q || $el.dataset.search.includes(q.toLowerCase())">
                                <input type="checkbox" class="form-check-input mt-1" name="disbursements[]" value="{{ $disbursement->getKey() }}" data-pick
                                    @checked(in_array((string) $disbursement->getKey(), old('disbursements', []), true))>
                                <span>
                                    <span class="d-block">{{ $disbursement->description }}</span>
                                    <span class="text-meta">{{ $disbursement->incurred_on->format('j M Y') }} · Disbursement · {{ $rand($disbursement->amount_cents) }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                @if ($timePeople->isNotEmpty())
                    <h4 class="text-meta text-uppercase mt-3 mb-2">Time on top</h4>
                    <p class="form-hint">The hourly rate for each person's time, if you add work with time on top. A rate already on this bill is used unless you change it.</p>

                    @foreach ($timePeople as $authorId => $narrations)
                        <x-ui.input :label="$narrations->first()->author->name.' — R an hour'" name="time_rates[{{ $authorId }}]" id="add_rate_{{ $loop->index }}"
                            type="number" step="0.01" min="0"
                            :value="old('time_rates.'.$authorId, isset($ratesOnBill[$authorId]) ? number_format($ratesOnBill[$authorId] / 100, 2, '.', '') : null)"
                            :error="$addErrors->first('time_rates.'.$authorId)" />
                    @endforeach
                @endif
            @endif

            <x-slot:footer>
                <div class="d-flex align-items-center justify-content-between">
                    <x-ui.button variant="outline-secondary" size="sm" x-on:click="close()">Cancel</x-ui.button>
                    <x-ui.button type="submit" variant="primary" size="sm">Add to the bill</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.sheet>
    @endif

    @if ($bill->isIssued())
        @can('voidBills', $bill->matter)
            <x-ui.sheet name="void-bill" title="Void {{ $bill->number }}" lede="For a bill issued in error. It keeps its number and stays on record; nothing is owed on it, and its work can be billed again."
                bag="bill_void" :action="route('bill.void', $bill)">
                <div class="mb-3">
                    <label class="form-label form-label-mono" for="void_reason">Reason</label>
                    <textarea class="form-control @error('reason', 'bill_void') is-invalid @enderror" id="void_reason" name="reason" rows="3">{{ old('reason') }}</textarea>
                    @error('reason', 'bill_void')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <x-slot:footer>
                    <div class="d-flex align-items-center justify-content-between">
                        <x-ui.button variant="outline-secondary" size="sm" x-on:click="close()">Cancel</x-ui.button>
                        <x-ui.button type="submit" variant="danger" size="sm">Void the bill</x-ui.button>
                    </div>
                </x-slot:footer>
            </x-ui.sheet>
        @endcan
    @endif
@endsection
