@extends('layouts.app')

@section('title', ($bill->number ?? 'Draft bill').' — Example Firm')

@section('content')
    @php
        $rand = fn (int $cents) => 'R '.number_format($cents / 100, 2, '.', ' ');
        $percent = rtrim(rtrim(number_format($bill->drawing_fee_percent_hundredths / 100, 2, '.', ''), '0'), '.');
        $tones = ['draft' => 'accent', 'issued' => 'success', 'void' => 'neutral'];
        $units = fn ($line) => rtrim(rtrim((string) $line->units, '0'), '.');
    @endphp

    <div class="mb-3 d-flex align-items-start justify-content-between gap-3">
        <div>
            <a href="{{ route('matter.show', [$bill->matter, 'tab' => 'billing']) }}" class="text-meta">← {{ $bill->matter->reference }}</a>
            <h1 class="mb-1">
                {{ $bill->kind->label() }} {{ $bill->number }}
                <x-ui.stage-badge :tone="$tones[$bill->status->value]" class="ms-2">{{ $bill->status->label() }}</x-ui.stage-badge>
            </h1>
            <p class="text-secondary mb-0">
                To {{ $bill->addressee }}
                @if ($bill->scale) · scale {{ $bill->scale->value }} @endif
                @if ($bill->period_from || $bill->period_to)
                    · work {{ $bill->period_from ? 'from '.$bill->period_from->format('j M Y') : '' }} {{ $bill->period_to ? 'up to '.$bill->period_to->format('j M Y') : '' }}
                @endif
                · drawn {{ $bill->created_at->format('j M Y') }}{{ $bill->draftedBy ? ' by '.$bill->draftedBy->name : '' }}
            </p>
        </div>

        @if ($bill->isDraft())
            <form method="POST" action="{{ route('bill.destroy', $bill) }}"
                data-confirm="Delete this draft? The work and disbursements on it become free to bill again."
                data-confirm-title="Delete draft" data-confirm-action="Delete" data-confirm-tone="danger" data-confirm-icon="trash">
                @csrf
                @method('DELETE')
                <x-ui.button type="submit" variant="outline-danger" size="sm">Delete draft</x-ui.button>
            </form>
        @endif
    </div>

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
                            </td>
                            <td class="text-end text-nowrap">{{ $line->fee_cents ? $rand($line->fee_cents) : '' }}</td>
                            <td class="text-end text-nowrap">{{ $line->disbursement_cents ? $rand($line->disbursement_cents) : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-end">Fees</td>
                        <td class="text-end text-nowrap">{{ $rand($bill->fees_cents) }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="text-end">Drawing fee ({{ $percent }}%)</td>
                        <td class="text-end text-nowrap">{{ $rand($bill->drawing_fee_cents) }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="text-end">Disbursements</td>
                        <td></td>
                        <td class="text-end text-nowrap">{{ $rand($bill->disbursements_cents) }}</td>
                    </tr>
                    <tr>
                        <td colspan="3" class="text-end"><strong>Total due</strong></td>
                        <td colspan="2" class="text-end text-nowrap"><strong>{{ $rand($bill->total_cents) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection
