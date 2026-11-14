<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Billing;

use App\Enums\Billing\BillKind;
use App\Enums\Billing\BillLineKind;
use App\Models\Billing\Bill;
use App\Models\Billing\BillLine;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_claim_names_the_kind_of_bill_and_what_the_line_came_from(): void
    {
        $this->assertSame('statement:narration:abc', BillLine::claimFor(BillKind::Statement, BillLineKind::Work, 'abc'));
        $this->assertSame('bill_of_cost:time:abc', BillLine::claimFor(BillKind::BillOfCost, BillLineKind::TimeOnTop, 'abc'));
        $this->assertSame('statement:disbursement:7', BillLine::claimFor(BillKind::Statement, BillLineKind::Disbursement, 7));
    }

    public function test_the_same_work_cannot_be_claimed_by_two_live_bills_of_one_kind(): void
    {
        $claim = BillLine::claimFor(BillKind::Statement, BillLineKind::Work, 'abc');
        BillLine::factory()->create(['claim' => $claim]);

        $this->expectException(QueryException::class);

        BillLine::factory()->create(['claim' => $claim]);
    }

    public function test_the_same_work_can_be_on_a_statement_and_a_bill_of_cost(): void
    {
        BillLine::factory()->create(['claim' => BillLine::claimFor(BillKind::Statement, BillLineKind::Work, 'abc')]);
        BillLine::factory()->create(['claim' => BillLine::claimFor(BillKind::BillOfCost, BillLineKind::Work, 'abc')]);

        $this->assertSame(2, BillLine::count());
    }

    public function test_live_bills_are_drafts_and_issued_ones(): void
    {
        $draft = Bill::factory()->create();
        $issued = Bill::factory()->issued()->create();
        Bill::factory()->void()->create();

        $this->assertEqualsCanonicalizing([$draft->id, $issued->id], Bill::query()->live()->pluck('id')->all());
    }

    public function test_a_lines_amount_is_its_fee_or_its_disbursement(): void
    {
        $this->assertSame(22000, BillLine::factory()->make()->amount());
        $this->assertSame(45000, BillLine::factory()->make(['kind' => BillLineKind::Disbursement, 'fee_cents' => 0, 'disbursement_cents' => 45000])->amount());
    }
}
