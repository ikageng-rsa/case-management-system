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
