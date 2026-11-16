<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Billing;

use App\Enums\Billing\BillKind;
use App\Enums\Billing\BillLineKind;
use App\Enums\Billing\TariffScale;
use App\Enums\Narration\ActivityMeasure;
use App\Models\Billing\Bill;
use App\Models\Billing\Disbursement;
use App\Models\Billing\FeeAgreement;
use App\Models\Billing\TariffItem;
use App\Models\Billing\TariffSchedule;
use App\Models\Matter\Matter;
use App\Models\Narration\Narration;
use App\Models\Reference\ActivityType;
use App\Models\User\Role;
use App\Models\User\User;
use App\Services\Billing\AddWorkToBill;
use App\Services\Billing\BillRefused;
use App\Services\Billing\DraftBill;
use App\Services\Billing\RemoveBillLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddWorkToBillTest extends TestCase
{
    use RefreshDatabase;

    protected Matter $matter;

    protected User $attorney;

    protected ActivityType $drafting;

    protected ActivityType $travel;

    protected Bill $bill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->matter = Matter::factory()->create(['tariff_scale' => TariffScale::A]);
        FeeAgreement::factory()->for($this->matter)->create();
        $this->attorney = tap(User::factory()->create(['name' => 'Lerato Khumalo']))->assignRole(Role::findOrCreate('attorney'));

        $this->drafting = ActivityType::factory()->measuredIn(ActivityMeasure::Page)->create(['code' => 'DRAFT']);
        $this->travel = ActivityType::factory()->measuredIn(ActivityMeasure::Kilometre)->create(['code' => 'TRAVEL', 'charges_time_on_top' => true]);
        $tariff = TariffSchedule::factory()->startingOn('2026-01-01')->create();
        TariffItem::factory()->for($tariff, 'schedule')->create(['activity_type_id' => $this->drafting->id, 'scale_a_cents' => 4000]);
        TariffItem::factory()->for($tariff, 'schedule')->create(['activity_type_id' => $this->travel->id, 'scale_a_cents' => 650]);

        $this->work($this->travel, 80, '2026-06-05', minutes: 60);
        $this->bill = app(DraftBill::class)->draft($this->matter, BillKind::Statement, 'Client', $this->attorney, timeRates: [$this->attorney->id => 176000]);
    }

    protected function work(ActivityType $type, float $quantity, string $on, ?int $minutes = null): Narration
    {
        return Narration::factory()->for($this->matter)->for($type)->for($this->attorney, 'author')
            ->create(['quantity' => $quantity, 'minutes' => $minutes, 'occurred_at' => $on]);
    }

    public function test_work_recorded_since_the_draft_is_added_in_date_order_and_the_totals_follow(): void
    {
        $travelled = $this->bill->lines->first()->narration_id;
        $earlier = $this->work($this->drafting, 4, '2026-06-01');
        $cost = Disbursement::factory()->for($this->matter)->create(['incurred_on' => '2026-06-10', 'amount_cents' => 45000]);

        $bill = app(AddWorkToBill::class)->add($this->bill, [$earlier->id], [$cost->id], $this->attorney);

        $this->assertSame([$earlier->id, $travelled], $bill->lines->take(2)->pluck('narration_id')->all());
        $this->assertSame(BillLineKind::Disbursement, $bill->lines->last()->kind);
        $this->assertSame([0, 1, 2, 3], $bill->lines->pluck('position')->all());
        // R 160 drafting + R 520 travel + R 1 760 time, and the R 450 disbursement.
        $this->assertSame(244000, $bill->fees_cents);
        $this->assertSame(45000, $bill->disbursements_cents);
    }

    public function test_work_taken_off_the_draft_can_be_put_back(): void
    {
        $drafting = $this->work($this->drafting, 4, '2026-06-01');
        $bill = app(AddWorkToBill::class)->add($this->bill, [$drafting->id], [], $this->attorney);
        app(RemoveBillLine::class)->remove($bill->lines->firstWhere('narration_id', $drafting->id), $this->attorney);

        $bill = app(AddWorkToBill::class)->add($this->bill->fresh(), [$drafting->id], [], $this->attorney);

        $this->assertSame(1, $bill->lines->where('narration_id', $drafting->id)->count());
    }

    public function test_time_on_top_uses_the_rate_already_on_the_bill_unless_one_is_given(): void
    {
        $more = $this->work($this->travel, 10, '2026-06-07', minutes: 30);

        $bill = app(AddWorkToBill::class)->add($this->bill, [$more->id], [], $this->attorney);
        $this->assertSame(88000, $bill->lines->where('narration_id', $more->id)->firstWhere('kind', BillLineKind::TimeOnTop)->fee_cents);

        $again = $this->work($this->travel, 10, '2026-06-08', minutes: 30);
        $bill = app(AddWorkToBill::class)->add($this->bill, [$again->id], [], $this->attorney, [$this->attorney->id => 200000]);
        $this->assertSame(100000, $bill->lines->where('narration_id', $again->id)->firstWhere('kind', BillLineKind::TimeOnTop)->fee_cents);
    }

    public function test_work_already_billed_or_on_an_issued_bill_is_refused(): void
    {
        $billed = $this->bill->lines->first()->narration_id;

        try {
            app(AddWorkToBill::class)->add($this->bill, [$billed], [], $this->attorney);
            $this->fail('Billed work was added again.');
        } catch (BillRefused $e) {
            $this->assertSame(['Choose the work or disbursements to add. What was chosen is already billed.'], $e->problems);
        }

        $this->bill->update(['status' => 'issued', 'number' => 'INV/2026/0001']);
        $this->expectException(BillRefused::class);
        app(AddWorkToBill::class)->add($this->bill->fresh(), [$this->work($this->drafting, 1, '2026-06-09')->id], [], $this->attorney);
    }
}
