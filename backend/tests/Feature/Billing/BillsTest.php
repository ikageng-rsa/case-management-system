<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Billing\TariffScale;
use App\Enums\Narration\ActivityMeasure;
use App\Models\Billing\Bill;
use App\Models\Billing\BillLine;
use App\Models\Billing\FeeAgreement;
use App\Models\Billing\TariffItem;
use App\Models\Billing\TariffSchedule;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\Narration\Narration;
use App\Models\Reference\ActivityType;
use App\Models\User\User;
use App\Settings\FirmSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillsTest extends TestCase
{
    use RefreshDatabase;

    protected Matter $matter;

    protected User $director;

    protected User $attorney;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);

        $this->director = tap(User::factory()->create(['name' => 'Thandiwe Mokoena']))->assignRole(RoleEnum::Director->value);
        $this->attorney = tap(User::factory()->create(['name' => 'Lerato Khumalo']))->assignRole(RoleEnum::Attorney->value);

        $this->matter = Matter::factory()->create(['tariff_scale' => TariffScale::A]);
        FeeAgreement::factory()->for($this->matter)->create();

        $travel = ActivityType::factory()->measuredIn(ActivityMeasure::Kilometre)->create(['code' => 'TRAVEL', 'name' => 'Travelling', 'charges_time_on_top' => true]);
        $tariff = TariffSchedule::factory()->startingOn('2026-01-01')->create();
        TariffItem::factory()->for($tariff, 'schedule')->create(['activity_type_id' => $travel->id, 'scale_a_cents' => 650]);

        Narration::factory()->for($this->matter)->for($travel)->for($this->attorney, 'author')
            ->create(['quantity' => 80, 'minutes' => 45, 'occurred_at' => '2026-06-03', 'body' => 'Travelled to court']);
    }

    public function test_a_bill_is_raised_from_the_billing_tab_with_a_rate_for_each_persons_time(): void
    {
        $this->actingAs($this->director)->get(route('matter.show', [$this->matter, 'tab' => 'billing']))
            ->assertOk()
            ->assertSee('Raise a bill')
            ->assertSee('Lerato Khumalo (0 h 45 min)');

        $response = $this->actingAs($this->director)->post(route('matter.bills.store', $this->matter), [
            'kind' => 'statement',
            'addressee' => $this->matter->client->full_name,
            'time_rates' => [$this->attorney->id => '1760'],
        ]);

        $bill = Bill::sole();
        $response->assertRedirect(route('bill.show', $bill));

        $this->actingAs($this->director)->get(route('bill.show', $bill))
            ->assertOk()
            ->assertSee('Statement of account')
            ->assertSee('Draft')
            ->assertSee('Travelled to court')
            ->assertSee('0.75 h at R 1 760.00 an hour')
            // R 520 for 80 km, R 1 320 for 45 minutes, a R 92 drawing fee.
            ->assertSee('R 1 840.00')
            ->assertSee('Drawing fee (5%)')
            ->assertSee('R 1 932.00');

        $this->actingAs($this->director)->get(route('matter.show', [$this->matter, 'tab' => 'billing']))->assertSee('Statement of account');
    }

    public function test_a_bill_that_cannot_be_drawn_lists_why_in_the_sheet(): void
    {
        $this->actingAs($this->director)
            ->from(route('matter.show', [$this->matter, 'tab' => 'billing']))
            ->post(route('matter.bills.store', $this->matter), ['kind' => 'statement', 'addressee' => 'Client'])
            ->assertRedirect(route('matter.show', [$this->matter, 'tab' => 'billing']))
            ->assertSessionHasErrorsIn('bill', 'bill');

        $this->assertSame(0, Bill::count());
    }

    public function test_a_draft_is_deleted_and_its_work_is_free_again_but_an_issued_bill_is_not(): void
    {
        $draft = Bill::factory()->for($this->matter)->create();
        $issued = Bill::factory()->for($this->matter)->issued()->create();

        $this->actingAs($this->director)->delete(route('bill.destroy', $draft))
            ->assertRedirect(route('matter.show', [$this->matter, 'tab' => 'billing']));
        $this->actingAs($this->director)->delete(route('bill.destroy', $issued))
            ->assertSessionHasErrorsIn('bill', 'bill');

        $this->assertModelMissing($draft);
        $this->assertModelExists($issued);
    }

    public function test_a_line_on_a_draft_is_changed_or_removed_from_the_bill_page(): void
    {
        $bill = Bill::factory()->for($this->matter)->create();
        $line = BillLine::factory()->for($bill)->create(['fee_cents' => 20000]);
        $other = BillLine::factory()->for($bill)->create(['fee_cents' => 5000]);

        $this->actingAs($this->director)->get(route('bill.show', $bill))->assertSee('aria-label="Change the amount"', false)->assertSee('aria-label="Remove the line"', false);

        $this->actingAs($this->director)->patch(route('bill.lines.update', [$bill, $line]), ['amount' => '150.00'])
            ->assertRedirect(route('bill.show', $bill));
        $this->actingAs($this->director)->get(route('bill.show', $bill))->assertSee('Changed from R 200.00 by Thandiwe Mokoena');

        $this->actingAs($this->director)->patch(route('bill.lines.update', [$bill, $line]), ['amount' => '-1'])
            ->assertSessionHasErrorsIn('bill_line_'.$line->id, 'amount');

        $this->actingAs($this->director)->delete(route('bill.lines.destroy', [$bill, $other]))->assertRedirect(route('bill.show', $bill));
        $this->assertModelMissing($other);
        $this->assertSame(15000, $bill->fresh()->fees_cents);
    }

    public function test_unbilled_work_is_found_and_added_from_the_bill_page(): void
    {
        $this->actingAs($this->director)->post(route('matter.bills.store', $this->matter), [
            'kind' => 'statement', 'addressee' => 'Client', 'time_rates' => [$this->attorney->id => '1760'],
        ]);
        $bill = Bill::sole();

        $this->actingAs($this->director)->get(route('bill.show', $bill))
            ->assertSee('Add work')
            ->assertSee('Everything recorded on this matter is already on a statement of account.');

        $late = Narration::factory()->for($this->matter)->for(ActivityType::where('code', 'TRAVEL')->sole())->for($this->attorney, 'author')
            ->create(['quantity' => 20, 'minutes' => null, 'occurred_at' => '2026-06-09', 'body' => 'Collected the court order']);

        $this->actingAs($this->director)->get(route('bill.show', $bill))
            ->assertSee('Collected the court order')
            ->assertSee('data-search="9 jun 2026 travelling travel collected the court order lerato khumalo"', false);

        $this->actingAs($this->director)->post(route('bill.lines.store', $bill), [])->assertSessionHasErrorsIn('bill_add');
        $this->actingAs($this->director)->post(route('bill.lines.store', $bill), ['narrations' => [$late->id]])
            ->assertRedirect(route('bill.show', $bill))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $bill->lines()->where('narration_id', $late->id)->count());
    }

    public function test_a_draft_is_issued_with_its_number_and_voided_with_a_reason(): void
    {
        $this->actingAs($this->director)->post(route('matter.bills.store', $this->matter), [
            'kind' => 'statement', 'addressee' => 'Client', 'time_rates' => [$this->attorney->id => '1760'],
        ]);
        $bill = Bill::sole();

        $this->actingAs($this->director)->get(route('bill.show', $bill))->assertSee('Issue');
        $this->actingAs($this->director)->post(route('bill.issue', $bill))->assertRedirect(route('bill.show', $bill));

        $bill->refresh();
        $this->assertTrue($bill->isIssued());
        $this->assertSame('INV/'.now()->year.'/0001', $bill->number);

        $this->actingAs($this->director)->get(route('bill.show', $bill))
            ->assertSee($bill->number)
            ->assertSee('issued '.now()->format('j M Y').' by Thandiwe Mokoena')
            ->assertDontSee('Add work')
            ->assertDontSee('aria-label="Remove the line"', false);

        $this->actingAs($this->director)->post(route('bill.void', $bill), ['reason' => ''])->assertSessionHasErrorsIn('bill_void', 'reason');
        $this->actingAs($this->director)->post(route('bill.void', $bill), ['reason' => 'Sent to the wrong client'])->assertRedirect(route('bill.show', $bill));

        $this->actingAs($this->director)->get(route('bill.show', $bill))->assertSee('Sent to the wrong client');

        // Its work is free again, so a new statement can take it.
        $this->actingAs($this->director)->post(route('matter.bills.store', $this->matter), [
            'kind' => 'statement', 'addressee' => 'Client', 'time_rates' => [$this->attorney->id => '1760'],
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, Bill::count());
    }

    public function test_issuing_and_voiding_are_their_own_permissions(): void
    {
        $secretary = tap(User::factory()->create())->assignRole(RoleEnum::Secretary->value);
        $secretary->givePermissionTo('draft_bills');
        $draft = BillLine::factory()->for(Bill::factory()->for($this->matter))->create()->bill;
        $issued = Bill::factory()->for($this->matter)->issued()->create();

        $this->actingAs($secretary)->get(route('bill.show', $draft))->assertOk()->assertDontSee('Issue this bill');
        $this->actingAs($secretary)->post(route('bill.issue', $draft))->assertForbidden();
        $this->actingAs($secretary)->post(route('bill.void', $issued), ['reason' => 'Wrong'])->assertForbidden();

        // Someone who only issues sees the bills and the issue button, but cannot change a draft.
        $issuer = tap(User::factory()->create())->assignRole(RoleEnum::Secretary->value);
        $issuer->givePermissionTo('issue_bills');
        $this->actingAs($issuer)->get(route('bill.show', $draft))->assertOk()->assertDontSee('Add work');
        $this->actingAs($issuer)->delete(route('bill.destroy', $draft))->assertForbidden();
        $this->actingAs($issuer)->post(route('bill.issue', $draft))->assertRedirect();
        $this->assertTrue($draft->fresh()->isIssued());
    }

    public function test_a_bill_downloads_as_a_pdf_named_by_its_number(): void
    {
        $firm = app(FirmSettings::class);
        $firm->name = 'Test Firm Inc.';
        $firm->save();
        $bill = BillLine::factory()->for(Bill::factory()->for($this->matter)->issued('INV/2026/0007'))->create()->bill;

        $response = $this->actingAs($this->director)->get(route('bill.pdf', $bill));

        $response->assertOk()->assertHeader('content-type', 'application/pdf')->assertDownload('INV-2026-0007.pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $html = view('pdf.bill', ['bill' => $bill->load(['matter', 'lines'])])->render();
        $this->assertStringContainsString('Test Firm Inc.', $html);
        $this->assertStringContainsString('INV/2026/0007', $html);
        $this->assertStringNotContainsString('class="stamp"', $html);
    }

    public function test_the_address_given_when_raising_a_bill_is_printed_under_attention_to(): void
    {
        $this->actingAs($this->director)->post(route('matter.bills.store', $this->matter), [
            'kind' => 'statement', 'addressee' => 'Mrs A Client', 'address' => "12 Main Street\nRustenburg",
            'time_rates' => [$this->attorney->id => '1760'],
        ])->assertSessionHasNoErrors();

        $bill = Bill::sole()->load(['matter', 'lines']);
        $this->actingAs($this->director)->get(route('bill.show', $bill))->assertSee('To Mrs A Client, 12 Main Street, Rustenburg');

        $html = view('pdf.bill', ['bill' => $bill])->render();
        $this->assertStringContainsString('<div class="attention">Attention to: Mrs A Client</div>', $html);
        $this->assertStringContainsString('12 Main Street<br />', $html);
    }

    public function test_the_pdf_quotes_the_case_number_the_invoice_number_and_date_under_a_heading_for_its_kind(): void
    {
        $firm = app(FirmSettings::class);
        $firm->name = 'Example Attorneys';
        $firm->save();
        $this->travelTo('2026-03-20 10:00');

        $statement = BillLine::factory()->for(Bill::factory()->for($this->matter)->issued('INV/2026/0018'))->create()->bill->load(['matter', 'lines']);
        $html = view('pdf.bill', ['bill' => $statement])->render();

        $this->assertStringContainsString('<th scope="row">Ref</th><td>'.$this->matter->reference.'</td>', $html);
        $this->assertStringContainsString('<th scope="row">Invoice no.</th><td>0018</td>', $html);
        $this->assertStringContainsString('<th scope="row">Date</th><td>20/03/26</td>', $html);
        $this->assertStringContainsString('Statement of account for fees due to Example Attorneys on attorney and own client scale', $html);

        $billOfCost = Bill::factory()->for($this->matter)->issued('INV/2026/0019')->create(['kind' => 'bill_of_cost', 'scale' => 'B']);
        $this->assertSame('Bill of cost of Example Attorneys on the party and party scale (scale B)', $billOfCost->heading('Example Attorneys'));
        $this->assertSame('0019', $billOfCost->shortNumber());
        $this->assertNull(Bill::factory()->make()->shortNumber());
    }

    public function test_a_draft_or_void_pdf_is_stamped_so_it_is_not_mistaken_for_an_owed_bill(): void
    {
        $draft = BillLine::factory()->for(Bill::factory()->for($this->matter))->create()->bill->load(['matter', 'lines']);
        $void = BillLine::factory()->for(Bill::factory()->for($this->matter)->void())->create()->bill->load(['matter', 'lines']);

        $this->assertStringContainsString('<div class="stamp">DRAFT</div>', view('pdf.bill', ['bill' => $draft])->render());
        $this->assertStringContainsString('<div class="stamp">VOID</div>', view('pdf.bill', ['bill' => $void])->render());
        $this->actingAs($this->director)->get(route('bill.pdf', $draft))->assertDownload('draft-'.substr($draft->id, 0, 8).'.pdf');

        MatterAssignment::factory()->for($this->matter)->for($this->attorney)->create();
        $this->actingAs($this->attorney)->get(route('bill.pdf', $draft))->assertForbidden();
    }

    public function test_a_line_is_reached_only_through_its_own_bill(): void
    {
        $bill = Bill::factory()->for($this->matter)->create();
        $elsewhere = BillLine::factory()->create();

        $this->actingAs($this->director)->delete(route('bill.lines.destroy', [$bill, $elsewhere]))->assertNotFound();
        $this->assertModelExists($elsewhere);
    }

    public function test_billing_needs_the_draft_bills_permission_on_a_matter_the_user_can_see(): void
    {
        MatterAssignment::factory()->for($this->matter)->for($this->attorney)->create();
        $bill = Bill::factory()->for($this->matter)->create();

        $this->actingAs($this->attorney)->get(route('matter.show', [$this->matter, 'tab' => 'billing']))->assertDontSee('Raise a bill');
        $this->actingAs($this->attorney)->post(route('matter.bills.store', $this->matter), ['kind' => 'statement', 'addressee' => 'Client'])->assertForbidden();
        $this->actingAs($this->attorney)->get(route('bill.show', $bill))->assertForbidden();
        $this->actingAs($this->attorney)->delete(route('bill.destroy', $bill))->assertForbidden();

        // Given the permission in role management, the attorney can bill.
        $this->attorney->givePermissionTo('draft_bills');
        $this->actingAs($this->attorney->fresh())->get(route('bill.show', $bill))->assertOk();
    }
}
