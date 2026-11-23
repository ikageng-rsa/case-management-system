<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\PaymentEmailOutcome;
use App\Mail\PaymentReceivedMail;
use App\Models\Billing\Payment;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailPaymentToClient
{
    /**
     * Email the client that a payment on their bill was received and what is
     * still owed, at the email address on file. It is sent once the payment is
     * saved, so it never announces one that was not recorded, and a failure to
     * send leaves the payment as it is. The log notes that it was sent, never
     * the address.
     */
    public function send(Payment $payment): PaymentEmailOutcome
    {
        $payment->loadMissing('bill.matter.client');

        $address = $payment->bill->matter->client->emailOnFile();

        if ($address === null) {
            return PaymentEmailOutcome::NoAddress;
        }

        try {
            Mail::to($address)->send(new PaymentReceivedMail($payment));
        } catch (Throwable $e) {
            report($e);

            return PaymentEmailOutcome::Failed;
        }

        $payment->recordEvent('emailed', 'The client was emailed about a '.$payment->auditLabel());

        return PaymentEmailOutcome::Sent;
    }
}
