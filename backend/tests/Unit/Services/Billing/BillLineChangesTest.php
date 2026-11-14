<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Billing;

use App\Enums\Billing\BillLineKind;
use App\Models\Billing\Bill;
use App\Models\Billing\BillLine;
use App\Models\User\User;
use App\Services\Billing\BillRefused;
use App\Services\Billing\ChangeBillLineAmount;
use App\Services\Billing\RemoveBillLine;
use App\Services\Billing\TotalBill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class BillLineChangesTest extends TestCase
{
    use RefreshDatabase;

    protected Bill $bill;

    protected BillLine $fee;

    protected BillLine $cost;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bill = Bill::factory()->create();
        $this->fee = BillLine::factory()->for($this->bill)->create(['fee_cents' => 20000, 'claim' => 'statement:narration:a']);
        $this->cost = BillLine::factory()->for($this->bill)->create([
            'kind' => BillLineKind::Disbursement, 'fee_cents' => 0, 'disbursement_cents' => 45000, 'claim' => 'statement:disbursement:1',
        ]);
        app(TotalBill::class)->total($this->bill);
    }

    public function test_a_changed_amount_keeps_the_original_and_who_changed_it_and_the_totals_follow(): void
    {
        $user = User::factory()->create(['name' => 'Thandiwe Mokoena']);

        app(ChangeBillLineAmount::class)->change($this->fee, 15000, $user);

        $this->assertSame(15000, $this->fee->fee_cents);
        $this->assertSame(20000, $this->fee->original_amount_cents);
        $this->assertTrue($this->fee->overriddenBy->is($user));

        $bill = $this->bill->fresh();
        $this->assertSame(15000, $bill->fees_cents);
        $this->assertSame(750, $bill->drawing_fee_cents);
        $this->assertSame(60750, $bill->total_cents);

        $this->assertSame(['description' => 'Drafted the summons', 'from_cents' => 20000, 'to_cents' => 15000],
            Activity::query()->where('event', 'line_changed')->sole()->properties->all());
    }

    public function test_a_disbursement_is_changed_in_its_own_column_and_setting_it_back_clears_the_change(): void
    {
        $user = User::factory()->create();

        app(ChangeBillLineAmount::class)->change($this->cost, 40000, $user);
        $this->assertSame(40000, $this->cost->disbursement_cents);
        $this->assertSame(0, $this->cost->fee_cents);

        app(ChangeBillLineAmount::class)->change($this->cost, 45000, $user);
        $this->assertNull($this->cost->original_amount_cents);
        $this->assertNull($this->cost->overridden_by);
    }

    public function test_a_removed_line_frees_its_work_and_the_totals_follow(): void
    {
        app(RemoveBillLine::class)->remove($this->fee, User::factory()->create());

        $this->assertModelMissing($this->fee);
        $this->assertSame(0, BillLine::where('claim', 'statement:narration:a')->count());
        $this->assertSame(45000, $this->bill->fresh()->total_cents);
    }

    public function test_the_last_line_is_not_removed_and_an_issued_bill_is_not_changed(): void
    {
        $user = User::factory()->create();
        app(RemoveBillLine::class)->remove($this->fee, $user);

        $this->expectException(BillRefused::class);
        app(RemoveBillLine::class)->remove($this->cost->fresh(), $user);
    }

    public function test_an_issued_bills_lines_stay_as_they_are(): void
    {
        $this->bill->update(['status' => 'issued', 'number' => 'INV/2026/0001']);

        $this->expectException(BillRefused::class);

        app(ChangeBillLineAmount::class)->change($this->fee->fresh(), 1, User::factory()->create());
    }
}
