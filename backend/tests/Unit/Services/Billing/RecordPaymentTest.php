<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Billing;

use App\Enums\Billing\FirmAccount;
use App\Enums\Billing\PaymentMethod;
use App\Enums\Billing\PaymentState;
use App\Models\Billing\Bill;
use App\Models\Billing\Payment;
use App\Models\User\User;
use App\Services\Billing\BillRefused;
use App\Services\Billing\PaymentRefused;
use App\Services\Billing\RecordPayment;
use App\Services\Billing\ReversePayment;
use App\Services\Billing\VoidBill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecordPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $secretary;

    protected Bill $bill;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('media-library.disk_name'));

        $this->secretary = User::factory()->create(['name' => 'Naledi Dlamini']);
        $this->bill = Bill::factory()->issued()->create(['total_cents' => 1000000]);
    }

    protected function pay(int $amountCents, ?Bill $bill = null, array $data = []): Payment
    {
        return app(RecordPayment::class)->record($bill ?? $this->bill, [
            'amount_cents' => $amountCents,
            'received_on' => '2026-11-16',
            'method' => PaymentMethod::Eft,
            'account' => FirmAccount::Trust,
            ...$data,
        ], $this->secretary, UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'));
    }

    protected function refusal(callable $act): string
    {
        try {
            $act();
        } catch (PaymentRefused $e) {
            return $e->getMessage();
        }

        $this->fail('The payment went through.');
    }

    public function test_a_payment_is_recorded_with_its_proof_and_the_bill_becomes_part_paid(): void
    {
        $this->assertSame(PaymentState::Unpaid, $this->bill->paymentState());

        $payment = $this->pay(400000, data: ['reference' => '  INV 0001 Mokoena ']);

        $this->assertSame(400000, $payment->amount_cents);
        $this->assertSame('2026-11-16', $payment->received_on->toDateString());
        $this->assertSame(PaymentMethod::Eft, $payment->method);
        $this->assertSame(FirmAccount::Trust, $payment->account);
        $this->assertSame('INV 0001 Mokoena', $payment->reference);
        $this->assertTrue($payment->recordedBy->is($this->secretary));
        $this->assertSame('proof.pdf', $payment->proof()->file_name);

        $this->bill->refresh();
        $this->assertSame(400000, $this->bill->paid_cents);
        $this->assertSame(600000, $this->bill->balanceCents());
        $this->assertSame(PaymentState::PartPaid, $this->bill->paymentState());
        $this->assertTrue($this->bill->isIssued());
    }

    public function test_paying_the_rest_settles_the_bill_and_nothing_more_is_taken(): void
    {
        $this->pay(400000);
        $this->pay(600000);

        $this->bill->refresh();
        $this->assertSame(0, $this->bill->balanceCents());
        $this->assertSame(PaymentState::Settled, $this->bill->paymentState());

        $this->assertSame('This bill is settled. Nothing more is owed on it.', $this->refusal(fn () => $this->pay(100)));
        $this->assertSame(2, Payment::count());
    }

    public function test_a_payment_is_never_more_than_what_is_still_owed(): void
    {
        $this->pay(400000);

        $this->assertSame('That is more than the R 6 000.00 still owed on this bill.', $this->refusal(fn () => $this->pay(600001)));
        $this->assertSame(400000, $this->bill->refresh()->paid_cents);
    }

    public function test_only_an_issued_bill_is_paid(): void
    {
        $message = 'Only an issued bill can be paid. A draft is issued first, and nothing is owed on a voided bill.';

        $this->assertSame($message, $this->refusal(fn () => $this->pay(100, Bill::factory()->create(['total_cents' => 1000]))));
        $this->assertSame($message, $this->refusal(fn () => $this->pay(100, Bill::factory()->void()->create(['total_cents' => 1000]))));
        $this->assertSame(0, Payment::count());
    }

    public function test_a_payment_recorded_in_error_is_reversed_and_kept_but_no_longer_counts(): void
    {
        $payment = $this->pay(400000);
        $director = User::factory()->create();

        app(ReversePayment::class)->reverse($payment, $director, '  Put on the wrong bill ');

        $payment->refresh();
        $this->assertTrue($payment->isReversed());
        $this->assertSame('Put on the wrong bill', $payment->reverse_reason);
        $this->assertTrue($payment->reversedBy->is($director));
        $this->assertNotNull($payment->proof());

        $this->bill->refresh();
        $this->assertSame(0, $this->bill->paid_cents);
        $this->assertSame(PaymentState::Unpaid, $this->bill->paymentState());

        $this->assertSame('This payment has already been reversed.', $this->refusal(fn () => app(ReversePayment::class)->reverse($payment, $director, 'Again')));

        // The full amount can be paid again.
        $this->pay(1000000);
        $this->assertSame(PaymentState::Settled, $this->bill->refresh()->paymentState());
    }

    public function test_a_bill_with_payments_is_not_voided_until_they_are_reversed(): void
    {
        $payment = $this->pay(400000);

        try {
            app(VoidBill::class)->void($this->bill->refresh(), $this->secretary, 'Wrong client');
            $this->fail('The bill was voided.');
        } catch (BillRefused $e) {
            $this->assertSame(['Payments have been recorded on this bill. Reverse them before voiding it.'], $e->problems);
        }

        app(ReversePayment::class)->reverse($payment, $this->secretary, 'Wrong client');

        $this->assertTrue(app(VoidBill::class)->void($this->bill->refresh(), $this->secretary, 'Wrong client')->isVoid());
        $this->assertNull($this->bill->paymentState());
    }
}
