@php
    $rand = fn (int $cents) => 'R '.number_format($cents / 100, 2, '.', ' ');
    $percent = rtrim(rtrim(number_format($bill->drawing_fee_percent_hundredths / 100, 2, '.', ''), '0'), '.');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $bill->kind->label() }} {{ $bill->number }}</title>
    {{-- A PDF is printed on its own, so its look is set here rather than by the app's stylesheet. --}}
    <style>
        @page { margin: 28mm 18mm 22mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #1f2933; }
        h1 { font-size: 16pt; margin: 0 0 4pt; }
        .firm { font-size: 13pt; font-weight: bold; }
        .meta { color: #52606d; font-size: 9pt; }
        .head { width: 100%; margin-bottom: 18pt; }
        .head td { vertical-align: top; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 10pt; }
        table.lines th { text-align: left; border-bottom: 1px solid #9aa5b1; padding: 4pt 4pt; font-size: 9pt; }
        table.lines td { border-bottom: 1px solid #e4e7eb; padding: 4pt 4pt; vertical-align: top; }
        .money { text-align: right; white-space: nowrap; }
        .totals td { border-bottom: none; padding: 3pt 4pt; }
        .grand td { border-top: 1px solid #1f2933; font-weight: bold; }
        .stamp { position: fixed; top: 40%; left: 15%; font-size: 72pt; color: #d64545; opacity: 0.15; transform: rotate(-30deg); }
    </style>
</head>
<body>
    @if ($bill->isDraft())
        <div class="stamp">DRAFT</div>
    @elseif ($bill->isVoid())
        <div class="stamp">VOID</div>
    @endif

    <table class="head">
        <tr>
            <td>
                <div class="firm">{{ config('firm.name') }}</div>
                @foreach (config('firm.address') as $line)
                    <div class="meta">{{ $line }}</div>
                @endforeach
                @if (config('firm.phone') || config('firm.email'))
                    <div class="meta">{{ collect([config('firm.phone'), config('firm.email')])->filter()->implode(' · ') }}</div>
                @endif
            </td>
            <td style="text-align: right;">
                <h1>{{ $bill->kind->label() }}</h1>
                <div>{{ $bill->number ?? 'Draft, not issued' }}</div>
                <div class="meta">{{ ($bill->issued_at ?? $bill->created_at)->format('j F Y') }}</div>
            </td>
        </tr>
    </table>

    <table class="head">
        <tr>
            <td>
                <div class="meta">To</div>
                <div>{{ $bill->addressee }}</div>
            </td>
            <td style="text-align: right;">
                <div class="meta">Matter</div>
                <div>{{ $bill->matter->reference }}</div>
                @if ($bill->matter->title)
                    <div class="meta">{{ $bill->matter->title }}</div>
                @endif
                @if ($bill->scale)
                    <div class="meta">Tariff scale {{ $bill->scale->value }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th style="width: 14%;">Date</th>
                <th style="width: 10%;">Item</th>
                <th>Description</th>
                <th class="money" style="width: 14%;">Fees</th>
                <th class="money" style="width: 16%;">Disbursements</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($bill->lines as $line)
                <tr>
                    <td>{{ $line->occurred_on->format('j M Y') }}</td>
                    <td>{{ $line->item }}</td>
                    <td>{{ $line->description }}</td>
                    <td class="money">{{ $line->kind->isFee() ? $rand($line->fee_cents) : '' }}</td>
                    <td class="money">{{ $line->kind->isFee() ? '' : $rand($line->disbursement_cents) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tbody class="totals">
            <tr><td colspan="3" class="money">Fees</td><td class="money">{{ $rand($bill->fees_cents) }}</td><td></td></tr>
            <tr><td colspan="3" class="money">Drawing fee ({{ $percent }}%)</td><td class="money">{{ $rand($bill->drawing_fee_cents) }}</td><td></td></tr>
            <tr><td colspan="3" class="money">Disbursements</td><td></td><td class="money">{{ $rand($bill->disbursements_cents) }}</td></tr>
            <tr class="grand"><td colspan="3" class="money">Total due</td><td colspan="2" class="money">{{ $rand($bill->total_cents) }}</td></tr>
        </tbody>
    </table>
</body>
</html>
