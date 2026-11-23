<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/* What became of the email telling a client about a payment, so the person recording it is told. */
enum PaymentEmailOutcome: string
{
    case Sent = 'sent';
    case NoAddress = 'no_address';
    case Failed = 'failed';

    public function message(): string
    {
        return match ($this) {
            self::Sent => 'The client was emailed the amount received and the balance.',
            self::NoAddress => 'No email was sent: the client has no email address on file.',
            self::Failed => 'The email to the client could not be sent. Let them know another way.',
        };
    }
}
