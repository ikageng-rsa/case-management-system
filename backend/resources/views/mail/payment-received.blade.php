<x-mail::message>
# Payment received

Dear {{ $addressee }},

We received your payment of **{{ $received }}** on {{ $receivedOn }}, on invoice {{ $number }} (our reference {{ $reference }}). Thank you.

<x-mail::table>
| | |
|:--|--:|
| Invoice total | {{ $total }} |
| Paid to date | {{ $paid }} |
| **Balance** | **{{ $balance }}** |
</x-mail::table>

@if ($settled)
This invoice is now settled. Nothing more is owed on it.
@else
{{ $balance }} is still owed on this invoice.
@endif

Your receipt is attached.

Regards,<br>
{{ $firm }}
</x-mail::message>
