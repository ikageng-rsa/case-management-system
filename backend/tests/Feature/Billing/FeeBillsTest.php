<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Billing\BillKind;
use App\Models\Billing\Bill;
use App\Models\Billing\FeeAgreement;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\User\User;
use App\Services\Billing\DraftFeeBill;
use App\Settings\InvoiceSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeeBillsTest extends TestCase
{
    use RefreshDatabase;

    protected Matter $matter;

    protected User $director;

    protected FeeAgreement $agreement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);

        $this->director = tap(User::factory()->create(['name' => 'Thandiwe Mokoena']))->assignRole(RoleEnum::Director->value);
        $this->matter = Matter::factory()->create();
        $this->agreement = FeeAgreement::factory()->for($this->matter)->contingency(25)->finalised(48000000)->create();
    }

    public function test_a_fee_bill_is_raised_from_a_finalised_agreement_on_the_billing_tab(): void
    {
        $this->actingAs($this->director)->get(route('matter.show', [$this->matter, 'tab' => 'billing']))
            ->assertOk()
            ->assertSee('Raise fee bill')
            ->assertSee('25% of the R 480 000.00 received, under the contingency agreement');

        $response = $this->actingAs($this->director)->post(route('fee-agreement.bill', $this->agreement), [
            'addressee' => $this->matter->client->full_name,
            'address' => "12 Loop Street\nCape Town",
        ]);

        $bill = Bill::sole();
        $response->assertRedirect(route('bill.show', $bill));

        $this->assertSame(BillKind::AgreementFee, $bill->kind);
        $this->assertSame(12000000, $bill->total_cents);
        $this->assertSame("12 Loop Street\nCape Town", $bill->addressee_address);

        $this->actingAs($this->director)->get(route('bill.show', $bill))
            ->assertOk()
            ->assertSee('Fee bill')
            ->assertSee('25% of R 480 000.00 received, under the contingency fee agreement')
            ->assertSee('R 120 000.00')
            ->assertDontSee('Drawing fee')
            ->assertDontSee('Add work');

        $this->actingAs($this->director)->get(route('matter.show', [$this->matter, 'tab' => 'billing']))
            ->assertOk()
            ->assertSee('Fee bill (draft)')
            ->assertDontSee('Raise fee bill');
    }

    public function test_a_fee_bill_prints_how_to_pay_and_no_drawing_fee(): void
    {
        $settings = app(InvoiceSettings::class);
        $settings->paymentTerms = 'Payable within 30 days.';
        $settings->save();

        $bill = app(DraftFeeBill::class)->draft($this->agreement, 'Mrs A Client', $this->director, "12 Main Street\nRustenburg");

        $html = view('pdf.bill', ['bill' => $bill->load(['matter', 'lines'])])->render();

        $this->assertStringContainsString('under the fee agreement', $html);
        $this->assertStringContainsString('25% of R 480 000.00 received, under the contingency fee agreement', $html);
        $this->assertStringContainsString('12 Main Street<br />', $html);
        $this->assertStringContainsString('<h2>Payment</h2>', $html);
        $this->assertStringContainsString('Payable within 30 days.', $html);
        $this->assertStringNotContainsString('Drawing fee', $html);

        $this->actingAs($this->director)->get(route('bill.pdf', $bill))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_an_agreement_with_a_fee_bill_is_not_billed_again_and_the_reason_is_shown(): void
    {
        $post = fn () => $this->actingAs($this->director)->post(route('fee-agreement.bill', $this->agreement), ['addressee' => 'The client']);

        $post();

        $post()->assertRedirect(route('matter.show', [$this->matter, 'tab' => 'billing']))
            ->assertSessionHasErrorsIn("fee_bill_{$this->agreement->id}", ['fee_bill' => 'This agreement already has a fee bill.']);

        $this->assertSame(1, Bill::count());
    }

    public function test_a_fee_bill_needs_someone_to_address_it_to(): void
    {
        $this->actingAs($this->director)->post(route('fee-agreement.bill', $this->agreement), ['addressee' => ''])
            ->assertSessionHasErrorsIn("fee_bill_{$this->agreement->id}", ['addressee']);

        $this->assertSame(0, Bill::count());
    }

    public function test_an_agreement_still_in_place_offers_no_fee_bill(): void
    {
        $matter = Matter::factory()->create();
        $active = FeeAgreement::factory()->for($matter)->contingency()->create();

        $this->actingAs($this->director)->get(route('matter.show', [$matter, 'tab' => 'billing']))
            ->assertOk()
            ->assertDontSee('Raise fee bill');

        $this->actingAs($this->director)->post(route('fee-agreement.bill', $active), ['addressee' => 'The client'])
            ->assertSessionHasErrorsIn("fee_bill_{$active->id}", ['fee_bill']);
    }

    public function test_raising_a_fee_bill_needs_the_permission_and_sight_of_the_matter(): void
    {
        $secretary = tap(User::factory()->create())->assignRole(RoleEnum::Secretary->value);
        MatterAssignment::factory()->for($this->matter)->for($secretary, 'user')->create();

        $this->actingAs($secretary)->get(route('matter.show', [$this->matter, 'tab' => 'billing']))
            ->assertOk()
            ->assertDontSee('Raise fee bill');

        $this->actingAs($secretary)->post(route('fee-agreement.bill', $this->agreement), ['addressee' => 'The client'])
            ->assertForbidden();

        $this->assertSame(0, Bill::count());
    }
}
