@php
    $firm = app(\App\Settings\FirmSettings::class);
    $letterhead = $firm->letterheadDataUri();
    $theme = app(\App\Settings\InvoiceSettings::class)->theme->value;
    $bill = $payment->bill;
    $rand = fn (int $cents) => 'R '.number_format($cents / 100, 2, '.', ' ');
    $balance = $payment->balanceAfterCents();
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $payment->receiptNumber() }}</title>
    <style>
        @page { margin: 28mm 18mm 22mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #1f2933; }
        .firm { font-size: 13pt; font-weight: bold; }
        .meta { color: #52606d; font-size: 9pt; }
        .letterhead { width: 100%; margin-bottom: 10pt; }
        .heading { border-top: 1px solid #1f2933; margin: 14pt 0 10pt; padding-top: 10pt; text-align: center; text-transform: uppercase; font-size: 11pt; }
        table.details { width: 100%; border-collapse: collapse; }
        table.details th { text-align: left; font-weight: normal; color: #52606d; width: 38%; padding: 4pt; border-bottom: 1px solid #e4e7eb; }
        table.details td { padding: 4pt; border-bottom: 1px solid #e4e7eb; }
        .amount td, .amount th { font-weight: bold; color: #1f2933; border-top: 1px solid #1f2933; }
        .note { margin-top: 18pt; }
    </style>
</head>
<body>
    @include('pdf.letterhead')

    <div class="heading">Receipt</div>

    <table class="details">
        <tr><th scope="row">Receipt no.</th><td>{{ $payment->receiptNumber() }}</td></tr>
        <tr><th scope="row">Received from</th><td>{{ $bill->addressee }}</td></tr>
        <tr><th scope="row">Date received</th><td>{{ $payment->received_on->format('j F Y') }}</td></tr>
        <tr><th scope="row">Paid by</th><td>{{ $payment->method->label() }}@if ($payment->reference), reference {{ $payment->reference }}@endif</td></tr>
        <tr><th scope="row">For</th><td>{{ $bill->kind->label() }} {{ $bill->number }}, our reference {{ $bill->matter->reference }}</td></tr>
        <tr class="amount"><th scope="row">Amount received</th><td>{{ $rand($payment->amount_cents) }}</td></tr>
        <tr><th scope="row">Balance after this payment</th><td>{{ $rand($balance) }}</td></tr>
    </table>

    <p class="note">
        {{ $balance === 0 ? 'This invoice is settled. Nothing more is owed on it.' : 'Thank you. '.$rand($balance).' is still owed on this invoice.' }}
    </p>
</body>
</html>
