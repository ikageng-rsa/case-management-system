@php
    $firm = app(\App\Settings\FirmSettings::class);
    $letterhead = $firm->letterheadDataUri();
    $invoices = app(\App\Settings\InvoiceSettings::class);
    $theme = $invoices->theme->value;
    $accent = $invoices->accentColour;
    $isFeeBill = $bill->kind === \App\Enums\Billing\BillKind::AgreementFee;
    // A statement and a fee bill ask the client to pay; a bill of cost goes to the other side for taxation.
    $asksForPayment = $bill->kind !== \App\Enums\Billing\BillKind::BillOfCost;
    // Once money has been received, the bill says so and what is left, so a copy printed later is not read as the full amount owed.
    $paymentState = $bill->paymentState();
    $hasPayments = $paymentState !== null && $bill->paid_cents > 0;
    $isSettled = $paymentState === \App\Enums\Billing\PaymentState::Settled;
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
        .stamp.paid { color: #2f9e44; left: 28%; }
        .balance td { font-weight: bold; }
        .letterhead { width: 100%; margin-bottom: 10pt; }
        .attention { font-weight: bold; text-transform: uppercase; }
        table.refs { width: 100%; border-collapse: collapse; }
        table.refs th, table.refs td { border: 1px solid #1f2933; padding: 2pt 5pt; text-align: left; font-weight: normal; }
        table.refs th { text-transform: uppercase; width: 45%; }
        .heading { border-top: 1px solid #1f2933; margin: 14pt 0 6pt; padding-top: 10pt; text-align: center; text-transform: uppercase; font-size: 11pt; }
        .address { text-transform: uppercase; }
        .payment { margin-top: 22pt; padding-top: 8pt; border-top: 1px solid #e4e7eb; }
        .payment h2 { font-size: 10pt; margin: 0 0 4pt; }

        {{--
            A theme styles everything under the letterhead: the address and reference
            box, the heading, the lines and the totals. The letterhead keeps its own
            colours. Modern also puts the firm's details in a band of the accent
            colour when there is no letterhead to print.
        --}}
        @if ($theme === 'classic')
            body { font-family: DejaVu Serif, serif; color: #111; }
            table.refs th, table.refs td { border: 1px solid #111; }
            table.refs th { font-weight: bold; font-size: 9pt; }
            .heading { border-top: 1.5pt solid #111; border-bottom: 1.5pt solid #111; padding: 6pt 0; letter-spacing: 0.5pt; }
            table.lines th { border-bottom: 1.5pt solid #111; }
            .grand td { border-top: 1.5pt double #111; }
            .payment { border-top: 1px solid #111; }
        @elseif ($theme === 'modern')
            .band { background: {{ $accent }}; color: #fff; margin: -28mm -18mm 18pt; padding: 16mm 18mm 8mm; }
            .band .meta { color: rgba(255, 255, 255, 0.85); }
            .attention { color: {{ $accent }}; }
            table.refs th, table.refs td { border: 1px solid {{ $accent }}; }
            table.refs th { background: {{ $accent }}; color: #fff; font-size: 9pt; }
            .heading { background: {{ $accent }}; color: #fff; border-top: none; padding: 6pt 0; font-weight: bold; }
            table.lines th { background: {{ $accent }}; color: #fff; border-bottom: none; }
            .grand td { border-top: 1.5pt solid {{ $accent }}; color: {{ $accent }}; }
            .payment { border-top: 1.5pt solid {{ $accent }}; }
            .payment h2 { color: {{ $accent }}; }
        @else
            body { color: #3e4c59; }
            .firm { font-weight: normal; letter-spacing: 0.5pt; }
            .attention { font-weight: normal; letter-spacing: 0.5pt; }
            table.refs th, table.refs td { border: none; border-bottom: 1px solid #e4e7eb; }
            table.refs th { color: #7b8794; font-size: 8pt; }
            .heading { border-top: none; color: #52606d; font-size: 10pt; letter-spacing: 1pt; }
            table.lines th { border-bottom: 1px solid #e4e7eb; color: #7b8794; font-weight: normal; text-transform: uppercase; font-size: 8pt; }
            table.lines td { border-bottom: 1px solid #f5f7fa; }
            .grand td { border-top: 1px solid {{ $accent }}; }
            .payment { border-top: 1px solid #f5f7fa; }
            .payment h2 { font-weight: normal; text-transform: uppercase; color: #7b8794; font-size: 8pt; letter-spacing: 1pt; }
        @endif
    </style>
</head>
<body>
    @if ($bill->isDraft())
        <div class="stamp">DRAFT</div>
    @elseif ($bill->isVoid())
        <div class="stamp">VOID</div>
    @elseif ($isSettled)
        <div class="stamp paid">PAID</div>
    @endif

    @include('pdf.letterhead')

    <table class="head" role="presentation">
        <tr>
            <td>
                <div class="attention">Attention to: {{ $bill->addressee }}</div>
                @if ($bill->addressee_address)
                    <div class="address">{!! nl2br(e($bill->addressee_address)) !!}</div>
                @endif
            </td>
            <td style="width: 42%;">
                <table class="refs">
                    <tr><th scope="row">Ref</th><td>{{ $bill->matter->reference }}</td></tr>
                    <tr><th scope="row">Invoice no.</th><td>{{ $bill->shortNumber() ?? 'Draft' }}</td></tr>
                    <tr><th scope="row">Date</th><td>{{ ($bill->issued_at ?? $bill->created_at)->format('d/m/y') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="heading">{{ $bill->heading($firm->name) }}</div>

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
            @unless ($isFeeBill)
                <tr><td colspan="3" class="money">Drawing fee ({{ $percent }}%)</td><td class="money">{{ $rand($bill->drawing_fee_cents) }}</td><td></td></tr>
            @endunless
            <tr><td colspan="3" class="money">Disbursements</td><td></td><td class="money">{{ $rand($bill->disbursements_cents) }}</td></tr>
            <tr class="grand"><td colspan="3" class="money">Total due</td><td colspan="2" class="money">{{ $rand($bill->total_cents) }}</td></tr>
            @if ($hasPayments)
                <tr><td colspan="3" class="money">Paid</td><td colspan="2" class="money">{{ $rand($bill->paid_cents) }}</td></tr>
                <tr class="balance"><td colspan="3" class="money">Balance</td><td colspan="2" class="money">{{ $rand($bill->balanceCents()) }}</td></tr>
            @endif
        </tbody>
    </table>
    @if ($asksForPayment && ! $isSettled)
        <div class="payment">
            <h2>Payment</h2>
            @if ($invoices->paymentTerms)
                <div>{{ $invoices->paymentTerms }}</div>
            @endif
            @if ($invoices->hasBankAccount())
                <div class="meta" style="margin-top: 4pt;">
                    {{ $invoices->accountName ?? $firm->name }} · {{ $invoices->bankName }}
                    · {{ $invoices->accountType ?? 'Account' }} {{ $invoices->accountNumber }}
                    @if ($invoices->branchCode) · Branch code {{ $invoices->branchCode }} @endif
                </div>
            @endif
            <div style="margin-top: 4pt;">Reference: <strong>{{ $bill->matter->reference }}</strong></div>
        </div>
    @endif
</body>
</html>
