<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Auth\Permission;
use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Billing\BillKind;
use App\Enums\Billing\PaymentState;
use App\Enums\Client\ContactKind;
use App\Mail\PaymentReceivedMail;
use App\Models\Billing\Bill;
use App\Models\Billing\BillLine;
use App\Models\Billing\Payment;
use App\Models\Client\ClientContact;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected Matter $matter;

    protected User $director;

    protected Bill $bill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
        Storage::fake(config('media-library.disk_name'));

        $this->director = tap(User::factory()->create(['name' => 'Thandiwe Mokoena']))->assignRole(RoleEnum::Director->value);
        $this->matter = Matter::factory()->create();
        $this->bill = BillLine::factory()
            ->for(Bill::factory()->for($this->matter)->issued('INV/2026/0007')->state(['fees_cents' => 1000000, 'total_cents' => 1000000]))
            ->create(['fee_cents' => 1000000])->bill;
    }

    /** @param  array<string, mixed>  $overrides */
    protected function payment(array $overrides = []): array
    {
        return [
            'amount' => '4000',
            'received_on' => now()->toDateString(),
            'method' => 'eft',
            'account' => 'trust',
            'reference' => 'INV 0007',
            'proof' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
            ...$overrides,
        ];
    }

    public function test_a_payment_is_recorded_on_an_issued_bill_and_the_balance_follows(): void
    {
        $this->actingAs($this->director)->get(route('bill.show', $this->bill))
            ->assertOk()
            ->assertSee('Unpaid')
            ->assertSee('Record payment')
            ->assertSee('R 10 000.00 is still owed.');

        $this->actingAs($this->director)->post(route('bill.payments.store', $this->bill), $this->payment())
            ->assertRedirect(route('bill.show', $this->bill))
            ->assertSessionHas('status', 'Payment recorded.');

        $payment = Payment::sole();
        $this->assertSame(400000, $payment->amount_cents);
        $this->assertSame(PaymentState::PartPaid, $this->bill->refresh()->paymentState());

        $this->actingAs($this->director)->get(route('bill.show', $this->bill))
            ->assertOk()
            ->assertSee('Part-paid')
            ->assertSee('EFT into the trust account')
            ->assertSee('Ref INV 0007')
            ->assertSee('by Thandiwe Mokoena')
            ->assertSee('Proof of payment')
            ->assertSee('R 6 000.00 is still owed.');

        $this->actingAs($this->director)->get(route('matter.show', [$this->matter, 'tab' => 'billing']))
            ->assertOk()
            ->assertSee('Part-paid')
            ->assertSee('R 6 000.00 still owed');

        $this->actingAs($this->director)->get(route('payment.proof', $payment))->assertOk();

        $this->actingAs($this->director)->post(route('bill.payments.store', $this->bill), $this->payment(['amount' => '6000']))
            ->assertSessionHas('status', 'Payment recorded. The bill is settled.');

        $this->actingAs($this->director)->get(route('bill.show', $this->bill))
            ->assertOk()
            ->assertSee('Settled')
            ->assertDontSee('Record payment');
    }

    public function test_the_client_is_emailed_about_a_payment_unless_the_box_is_unticked_or_the_bill_is_a_bill_of_cost(): void
    {
        Mail::fake();
        ClientContact::factory()->for($this->matter->client)->create(['kind' => ContactKind::Email, 'value' => 'client@example.com', 'is_primary' => true]);

        $this->actingAs($this->director)->get(route('bill.show', $this->bill))->assertSee('Email the client');

        $this->actingAs($this->director)->post(route('bill.payments.store', $this->bill), $this->payment(['amount' => '1000', 'email_client' => '1']))
            ->assertSessionHas('status', 'Payment recorded. The client was emailed the amount received and the balance.');
        Mail::assertSent(PaymentReceivedMail::class, 1);

        $this->actingAs($this->director)->post(route('bill.payments.store', $this->bill), $this->payment(['amount' => '1000', 'email_client' => '0']))
            ->assertSessionHas('status', 'Payment recorded.');
        Mail::assertSent(PaymentReceivedMail::class, 1);

        $billOfCost = Bill::factory()->for($this->matter)->issued('INV/2026/0008')->create(['kind' => BillKind::BillOfCost, 'total_cents' => 500000]);

        $this->actingAs($this->director)->get(route('bill.show', $billOfCost))->assertOk()->assertDontSee('Email the client');
        $this->actingAs($this->director)->post(route('bill.payments.store', $billOfCost), $this->payment(['amount' => '1000', 'email_client' => '1']))
            ->assertSessionHas('status', 'Payment recorded.');
        Mail::assertSent(PaymentReceivedMail::class, 1);
    }

    public function test_recording_a_payment_says_when_the_client_has_no_email_to_send_to(): void
    {
        Mail::fake();

        $this->actingAs($this->director)->post(route('bill.payments.store', $this->bill), $this->payment(['email_client' => '1']))
            ->assertSessionHas('status', 'Payment recorded. No email was sent: the client has no email address on file.');

        $this->assertSame(1, Payment::count());
        Mail::assertNothingSent();
    }

    public function test_a_payment_needs_its_proof_and_cannot_be_more_than_is_owed(): void
    {
        $this->actingAs($this->director)->post(route('bill.payments.store', $this->bill), $this->payment(['proof' => null]))
            ->assertSessionHasErrorsIn('payment', ['proof' => 'Attach the proof of payment. A payment is recorded with it.']);

        $this->actingAs($this->director)->post(route('bill.payments.store', $this->bill), $this->payment(['amount' => '10000.01']))
            ->assertSessionHasErrorsIn('payment', ['amount' => 'That is more than the R 10 000.00 still owed on this bill.']);

        $this->actingAs($this->director)->post(route('bill.payments.store', $this->bill), $this->payment(['received_on' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrorsIn('payment', ['received_on']);

        $this->assertSame(0, Payment::count());
    }

    public function test_a_payment_recorded_in_error_is_reversed_with_a_reason_and_stays_on_the_bill(): void
    {
        $this->actingAs($this->director)->post(route('bill.payments.store', $this->bill), $this->payment());
        $payment = Payment::sole();

        $this->actingAs($this->director)->post(route('payment.reverse', $payment), ['reason' => ''])
            ->assertSessionHasErrorsIn("payment_reverse_{$payment->id}", ['reason']);

        $this->actingAs($this->director)->post(route('payment.reverse', $payment), ['reason' => 'Put on the wrong bill'])
            ->assertRedirect(route('bill.show', $this->bill))
            ->assertSessionHas('status', 'Payment reversed. It no longer counts towards the bill.');

        $this->assertSame(PaymentState::Unpaid, $this->bill->refresh()->paymentState());

        $this->actingAs($this->director)->get(route('bill.show', $this->bill))
            ->assertOk()
            ->assertSee('Reversed')
            ->assertSee('Put on the wrong bill')
            ->assertSee('R 10 000.00 is still owed.');
    }

    public function test_a_bill_with_payments_is_not_voided(): void
    {
        $this->actingAs($this->director)->post(route('bill.payments.store', $this->bill), $this->payment());

        $this->actingAs($this->director)->post(route('bill.void', $this->bill), ['reason' => 'Wrong client'])
            ->assertSessionHasErrorsIn('bill', ['bill' => 'Payments have been recorded on this bill. Reverse them before voiding it.']);

        $this->assertTrue($this->bill->refresh()->isIssued());
    }

    public function test_recording_and_reversing_payments_needs_the_permission_and_sight_of_the_matter(): void
    {
        $payment = Payment::factory()->for($this->bill)->create();

        // An attorney drafts bills, so sees them, but does not record payments.
        $attorney = tap(User::factory()->create())->assignRole(RoleEnum::Attorney->value);
        MatterAssignment::factory()->for($this->matter)->for($attorney)->create();
        $this->assertFalse($attorney->can(Permission::RecordPayments->value));

        $this->actingAs($attorney)->post(route('bill.payments.store', $this->bill), $this->payment())->assertForbidden();
        $this->actingAs($attorney)->post(route('payment.reverse', $payment), ['reason' => 'No'])->assertForbidden();

        // Someone who may record payments, but cannot see the matter.
        $outsider = tap(User::factory()->create())->givePermissionTo(Permission::RecordPayments->value);

        $this->actingAs($outsider)->post(route('bill.payments.store', $this->bill), $this->payment())->assertForbidden();
        $this->actingAs($outsider)->get(route('payment.proof', $payment))->assertForbidden();

        $this->assertSame(1, Payment::count());
    }
}
