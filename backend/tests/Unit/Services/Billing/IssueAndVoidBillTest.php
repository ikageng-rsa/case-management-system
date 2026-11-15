<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Billing;

use App\Enums\Billing\BillStatus;
use App\Models\Billing\Bill;
use App\Models\Billing\BillLine;
use App\Models\User\User;
use App\Services\Billing\BillRefused;
use App\Services\Billing\IssueBill;
use App\Services\Billing\VoidBill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueAndVoidBillTest extends TestCase
{
    use RefreshDatabase;

    protected function draftWithALine(): Bill
    {
        return BillLine::factory()->create(['claim' => 'statement:narration:'.fake()->uuid()])->bill;
    }

    public function test_an_issued_bill_is_numbered_in_the_years_running_sequence(): void
    {
        $this->travelTo('2026-11-15 10:00');
        $user = User::factory()->create();

        $first = app(IssueBill::class)->issue($this->draftWithALine(), $user);
        $second = app(IssueBill::class)->issue($this->draftWithALine(), $user);

        $this->assertSame('INV/2026/0001', $first->number);
        $this->assertSame('INV/2026/0002', $second->number);
        $this->assertSame(BillStatus::Issued, $first->status);
        $this->assertTrue($first->issuedBy->is($user));
        $this->assertSame('2026-11-15', $first->issued_at->toDateString());

        $this->travelTo('2027-01-02 09:00');
        $this->assertSame('INV/2027/0001', app(IssueBill::class)->issue($this->draftWithALine(), $user)->number);
    }

    public function test_the_sequence_carries_on_past_ten_thousand_and_voided_numbers_are_not_reused(): void
    {
        $this->travelTo('2026-11-15 10:00');
        Bill::factory()->issued('INV/2026/9999')->create();
        Bill::factory()->void()->create(['number' => 'INV/2026/10000']);

        $this->assertSame('INV/2026/10001', app(IssueBill::class)->issue($this->draftWithALine(), User::factory()->create())->number);
    }

    public function test_only_a_draft_with_lines_is_issued(): void
    {
        $user = User::factory()->create();

        $this->expectException(BillRefused::class);
        app(IssueBill::class)->issue(Bill::factory()->create(), $user);
    }

    public function test_an_issued_bill_is_not_issued_again(): void
    {
        $this->expectException(BillRefused::class);

        app(IssueBill::class)->issue(Bill::factory()->issued()->create(), User::factory()->create());
    }

    public function test_a_voided_bill_keeps_its_number_and_reason_and_frees_its_work(): void
    {
        $user = User::factory()->create();
        $bill = app(IssueBill::class)->issue($this->draftWithALine(), $user);

        app(VoidBill::class)->void($bill, $user, '  Sent to the wrong client  ');

        $this->assertSame(BillStatus::Void, $bill->status);
        $this->assertSame('INV/'.now()->year.'/0001', $bill->number);
        $this->assertSame('Sent to the wrong client', $bill->void_reason);
        $this->assertTrue($bill->voidedBy->is($user));
        $this->assertNull($bill->lines()->first()->claim);
    }

    public function test_a_draft_is_deleted_not_voided(): void
    {
        $this->expectException(BillRefused::class);

        app(VoidBill::class)->void($this->draftWithALine(), User::factory()->create(), 'Wrong');
    }
}
