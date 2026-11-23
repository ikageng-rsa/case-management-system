<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Billing;

use App\Enums\Billing\BillKind;
use App\Enums\Billing\BillLineKind;
use App\Enums\Billing\DrawingFeeBase;
use App\Enums\Billing\TariffScale;
use App\Enums\Client\ContactKind;
use App\Enums\Narration\ActivityMeasure;
use App\Models\Billing\Bill;
use App\Models\Billing\BillLine;
use App\Models\Billing\Disbursement;
use App\Models\Billing\FeeAgreement;
use App\Models\Billing\TariffItem;
use App\Models\Billing\TariffSchedule;
use App\Models\Client\ClientContact;
use App\Models\Matter\Matter;
use App\Models\Narration\Narration;
use App\Models\Reference\ActivityType;
use App\Models\User\Role;
use App\Models\User\User;
use App\Services\Billing\BillRefused;
use App\Services\Billing\DraftBill;
use App\Settings\BillingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DraftBillTest extends TestCase
{
    use RefreshDatabase;

    protected Matter $matter;

    protected User $attorney;

    protected ActivityType $drafting;

    protected ActivityType $travel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->matter = Matter::factory()->create(['tariff_scale' => TariffScale::A]);
        FeeAgreement::factory()->for($this->matter)->create();

        $this->attorney = tap(User::factory()->create(['name' => 'Lerato Khumalo']))->assignRole(Role::findOrCreate('attorney'));
        $this->drafting = ActivityType::factory()->measuredIn(ActivityMeasure::Page)->create(['code' => 'DRAFT', 'name' => 'Drafting']);
        $this->travel = ActivityType::factory()->measuredIn(ActivityMeasure::Kilometre)->create(['code' => 'TRAVEL', 'name' => 'Travelling', 'charges_time_on_top' => true]);

        $tariff = TariffSchedule::factory()->startingOn('2026-01-01')->create(['name' => 'Tariff 2026']);
        TariffItem::factory()->for($tariff, 'schedule')->create(['activity_type_id' => $this->drafting->id, 'scale_a_cents' => 4000, 'scale_b_cents' => 5500]);
        TariffItem::factory()->for($tariff, 'schedule')->create(['activity_type_id' => $this->travel->id, 'scale_a_cents' => 650]);
    }

    protected function work(ActivityType $type, float $quantity, string $on = '2026-06-01', ?int $minutes = null): Narration
    {
        return Narration::factory()->for($this->matter)->for($type)->for($this->attorney, 'author')
            ->create(['quantity' => $quantity, 'minutes' => $minutes, 'occurred_at' => $on, 'body' => "{$type->name} on {$on}"]);
    }

    protected function draft(BillKind $kind = BillKind::Statement, ...$options): Bill
    {
        return app(DraftBill::class)->draft($this->matter, $kind, 'Thaddeus Hodkiewicz', $this->attorney, ...$options);
    }

    /** @return list<string> */
    protected function refusal(callable $draw): array
    {
        try {
            $draw();
        } catch (BillRefused $e) {
            return $e->problems;
        }

        $this->fail('The bill was drawn.');
    }

    public function test_a_statement_is_drawn_from_the_unbilled_work_time_and_disbursements_with_the_drawing_fee(): void
    {
        $this->work($this->drafting, 4, '2026-06-01');
        $this->work($this->travel, 80, '2026-06-03', minutes: 45);
        Disbursement::factory()->for($this->matter)->create(['incurred_on' => '2026-06-02', 'amount_cents' => 45000]);

        $bill = $this->draft(timeRates: [$this->attorney->id => 176000]);

        $this->assertTrue($bill->isDraft());
        $this->assertSame(TariffScale::A, $bill->scale);
        $this->assertSame([BillLineKind::Work, BillLineKind::Disbursement, BillLineKind::Work, BillLineKind::TimeOnTop], $bill->lines->pluck('kind')->all());

        // 4 pages at R 40 = R 160; 80 km at R 6.50 = R 520; 45 minutes at R 1 760 an hour = R 1 320.
        $this->assertSame([16000, 0, 52000, 132000], $bill->lines->pluck('fee_cents')->all());
        $this->assertSame(200000, $bill->fees_cents);
        $this->assertSame(500, $bill->drawing_fee_percent_hundredths);
        $this->assertSame(10000, $bill->drawing_fee_cents, '5% of the fees only');
        $this->assertSame(45000, $bill->disbursements_cents);
        $this->assertSame(255000, $bill->total_cents);
        $this->assertTrue($bill->draftedBy->is($this->attorney));
    }

    public function test_the_drawing_fee_can_be_charged_on_the_disbursements_too(): void
    {
        $settings = app(BillingSettings::class);
        $settings->drawingFeeBase = DrawingFeeBase::FeesAndDisbursements;
        $settings->save();
        $this->work($this->drafting, 4);
        Disbursement::factory()->for($this->matter)->create(['amount_cents' => 45000, 'incurred_on' => '2026-06-02']);

        $this->assertSame(3050, $this->draft()->drawing_fee_cents, '5% of R 160 + R 450');
    }

    public function test_work_already_on_a_live_bill_of_the_kind_is_left_for_the_next_one(): void
    {
        $this->work($this->drafting, 4, '2026-06-01');
        $this->draft();

        $late = $this->work($this->drafting, 2, '2026-06-15');

        $next = $this->draft();

        $this->assertSame([$late->id], $next->lines->pluck('narration_id')->all());
    }

    public function test_work_on_a_voided_bill_is_billed_again(): void
    {
        $narration = $this->work($this->drafting, 4);
        $first = $this->draft();
        $first->update(['status' => 'void']);
        $first->lines()->update(['claim' => null]);

        $this->assertSame([$narration->id], $this->draft()->lines->pluck('narration_id')->all());
    }

    public function test_the_same_work_can_be_on_a_bill_of_cost_as_well_as_a_statement(): void
    {
        $narration = $this->work($this->drafting, 4);
        $this->draft();

        $billOfCost = $this->draft(BillKind::BillOfCost, scale: TariffScale::B);

        $this->assertSame([$narration->id], $billOfCost->lines->pluck('narration_id')->all());
        $this->assertSame(22000, $billOfCost->fees_cents, 'on scale B');
    }

    public function test_a_period_limits_the_bill_to_work_done_in_it(): void
    {
        $this->work($this->drafting, 4, '2026-05-31');
        $june = $this->work($this->drafting, 2, '2026-06-10');

        $bill = $this->draft(from: Carbon::parse('2026-06-01'), to: Carbon::parse('2026-06-30'));

        $this->assertSame([$june->id], $bill->lines->pluck('narration_id')->all());
        $this->assertSame('2026-06-30', $bill->period_to->toDateString());
    }

    public function test_nothing_is_drawn_while_work_cannot_be_priced_or_time_has_no_rate(): void
    {
        $perusal = ActivityType::factory()->measuredIn(ActivityMeasure::Page)->create(['name' => 'Perusal']);
        $this->work($perusal, 10, '2026-06-02');
        $this->work($this->travel, 80, '2026-06-03', minutes: 45);

        $problems = $this->refusal(fn () => $this->draft());

        $this->assertSame([
            '2 Jun 2026, Perusal: Tariff 2026 has no scale A rate for Perusal.',
            "Give an hourly rate for Lerato Khumalo's time on top.",
        ], $problems);
        $this->assertSame(0, Bill::count());
    }

    public function test_a_statement_needs_a_mandate_and_a_bill_of_cost_a_matter_before_a_court(): void
    {
        $this->work($this->drafting, 4);
        $this->matter->feeAgreements()->update(['kind' => 'contingency', 'percent_hundredths' => 2500]);

        $this->assertSame(['The client is charged under a Contingency agreement, not by statement.'], $this->refusal(fn () => $this->draft()));

        $plain = Matter::factory()->nonLitigation()->create();
        $this->assertSame(
            ['A bill of cost is for a matter before a court.'],
            $this->refusal(fn () => app(DraftBill::class)->draft($plain, BillKind::BillOfCost, 'RAF', $this->attorney)),
        );
    }

    public function test_there_has_to_be_something_to_bill_and_a_scale_to_bill_it_on(): void
    {
        $this->assertSame(['There is no unbilled work or disbursement on this matter for that period.'], $this->refusal(fn () => $this->draft()));

        $this->matter->update(['tariff_scale' => null]);
        $this->work($this->drafting, 4);
        $this->assertSame(['Choose the tariff scale to bill on. The matter has none set.'], $this->refusal(fn () => $this->draft()));
    }

    public function test_a_statement_goes_to_the_clients_postal_address_unless_another_is_given(): void
    {
        $this->work($this->drafting, 4);
        $client = $this->matter->client;
        ClientContact::factory()->for($client)->create(['kind' => ContactKind::Physical, 'value' => '1 Physical Road', 'is_primary' => true]);
        ClientContact::factory()->for($client)->create(['kind' => ContactKind::Postal, 'value' => "PO Box 1\nRustenburg\n0300"]);

        $this->assertSame("PO Box 1\nRustenburg\n0300", $this->draft()->addressee_address);

        $this->work($this->drafting, 1, '2026-06-02');
        $this->assertSame('12 Other Street', $this->draft(address: '  12 Other Street ')->addressee_address);
    }

    public function test_a_bill_of_cost_carries_only_the_address_given(): void
    {
        $this->work($this->drafting, 4);
        ClientContact::factory()->for($this->matter->client)->create(['kind' => ContactKind::Postal, 'value' => 'PO Box 1']);

        $this->assertNull($this->draft(BillKind::BillOfCost)->addressee_address);
    }

    public function test_non_billable_work_is_not_billed(): void
    {
        $admin = ActivityType::factory()->nonBillable()->create();
        Narration::factory()->for($this->matter)->for($admin)->create(['occurred_at' => '2026-06-01']);
        $this->work($this->drafting, 1);

        $this->assertSame(1, $this->draft()->lines->count());
        $this->assertSame(1, BillLine::count());
    }
}
