<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Billing\Payment;
use App\Settings\FirmSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/*
 * Tells a client that a payment on their bill was received, and what is still
 * owed on it. The figures are read when it is built, so it says what the bill
 * stood at straight after the payment.
 */
class PaymentReceivedMail extends Mailable
{
    public function __construct(
        public readonly Payment $payment,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Payment received on invoice '.$this->payment->bill->number);
    }

    public function content(): Content
    {
        $bill = $this->payment->bill;

        return new Content(markdown: 'mail.payment-received', with: [
            'firm' => app(FirmSettings::class)->name,
            'addressee' => $bill->addressee,
            'number' => $bill->number,
            'reference' => $bill->matter->reference,
            'received' => $this->rand($this->payment->amount_cents),
            'receivedOn' => $this->payment->received_on->format('j F Y'),
            'total' => $this->rand($bill->total_cents),
            'paid' => $this->rand($bill->paid_cents),
            'balance' => $this->rand($bill->balanceCents()),
            'settled' => $bill->balanceCents() === 0,
        ]);
    }

    protected function rand(int $cents): string
    {
        return 'R '.number_format($cents / 100, 2, '.', ' ');
    }
}
