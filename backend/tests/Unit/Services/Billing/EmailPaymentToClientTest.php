<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Billing;

use App\Enums\Billing\PaymentEmailOutcome;
use App\Enums\Client\ContactKind;
use App\Mail\PaymentReceivedMail;
use App\Models\Billing\Bill;
use App\Models\Billing\Payment;
use App\Models\Client\ClientContact;
use App\Services\Billing\EmailPaymentToClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class EmailPaymentToClientTest extends TestCase
{
    use RefreshDatabase;

    protected Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $bill = Bill::factory()->issued('INV/2026/0007')->create(['addressee' => 'Mrs A Client', 'total_cents' => 1000000, 'paid_cents' => 400000]);
        $this->payment = Payment::factory()->for($bill)->create(['amount_cents' => 400000, 'received_on' => '2026-11-16']);
    }

    protected function clientEmail(string $address, bool $primary = false): void
    {
        ClientContact::factory()->for($this->payment->bill->matter->client)->create(['kind' => ContactKind::Email, 'value' => $address, 'is_primary' => $primary]);
    }

    public function test_the_client_is_told_the_amount_received_and_the_balance_at_their_primary_email(): void
    {
        Mail::fake();
        $this->clientEmail('other@example.com');
        $this->clientEmail('client@example.com', primary: true);

        $this->assertSame(PaymentEmailOutcome::Sent, app(EmailPaymentToClient::class)->send($this->payment));

        Mail::assertSent(PaymentReceivedMail::class, function (PaymentReceivedMail $mail): bool {
            $mail->assertHasSubject('Payment received on invoice INV/2026/0007');
            $mail->assertSeeInText('Dear Mrs A Client');
            $mail->assertSeeInText('R 4 000.00');
            $mail->assertSeeInText('16 November 2026');
            $mail->assertSeeInText('R 6 000.00 is still owed on this invoice.');

            return $mail->hasTo('client@example.com') && ! $mail->hasTo('other@example.com');
        });
    }

    public function test_a_payment_that_settles_the_bill_says_so(): void
    {
        $this->payment->bill->update(['paid_cents' => 1000000]);

        (new PaymentReceivedMail($this->payment->fresh()))
            ->assertSeeInText('This invoice is now settled. Nothing more is owed on it.')
            ->assertDontSeeInText('is still owed');
    }

    public function test_nothing_is_sent_to_a_client_with_no_email_on_file(): void
    {
        Mail::fake();
        ClientContact::factory()->for($this->payment->bill->matter->client)->create(['kind' => ContactKind::Mobile, 'value' => '0820000000']);

        $this->assertSame(PaymentEmailOutcome::NoAddress, app(EmailPaymentToClient::class)->send($this->payment));

        Mail::assertNothingSent();
    }

    public function test_an_email_that_cannot_be_sent_is_reported_and_leaves_the_payment_as_it_is(): void
    {
        $this->clientEmail('client@example.com', primary: true);
        Mail::shouldReceive('to')->andThrow(new RuntimeException('The mail server is down.'));

        $this->assertSame(PaymentEmailOutcome::Failed, app(EmailPaymentToClient::class)->send($this->payment));
        $this->assertFalse($this->payment->fresh()->isReversed());
    }
}
