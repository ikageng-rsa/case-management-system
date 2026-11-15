<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Billing;

use App\Enums\Billing\BillKind;
use App\Enums\Billing\BillLineKind;
use App\Models\Billing\Bill;
use App\Models\Billing\FeeAgreement;
use App\Models\User\User;
use App\Services\Billing\AddWorkToBill;
use App\Services\Billing\BillRefused;
use App\Services\Billing\ChangeBillLineAmount;
use App\Services\Billing\DeleteDraftBill;
use App\Services\Billing\DraftFeeBill;
use App\Services\Billing\IssueBill;
use App\Services\Billing\VoidBill;
use App\Settings\BillingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DraftFeeBillTest extends TestCase
{
    use RefreshDatabase;

    protected User $attorney;

    protected function setUp(): void
    {
        parent::setUp();

        $this->attorney = User::factory()->create(['name' => 'Lerato Khumalo']);
    }

    protected function draft(FeeAgreement $agreement, ?string $address = null): Bill
    {
        return app(DraftFeeBill::class)->draft($agreement, ' Thaddeus Hodkiewicz ', $this->attorney, $address);
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

    public function test_a_fee_bill_charges_the_agreed_share_of_what_was_received_on_one_line(): void
    {
        $agreement = FeeAgreement::factory()->contingency(25)->finalised(48000000)->create(['closed_at' => '2026-06-10 09:00:00']);

        $bill = $this->draft($agreement, "12 Loop Street\nCape Town");

        $this->assertSame(BillKind::AgreementFee, $bill->kind);
        $this->assertTrue($bill->isDraft());
        $this->assertTrue($bill->matter->is($agreement->matter));
        $this->assertSame('Thaddeus Hodkiewicz', $bill->addressee);
        $this->assertSame("12 Loop Street\nCape Town", $bill->addressee_address);
        $this->assertSame($this->attorney->id, $bill->drafted_by);

        $this->assertCount(1, $bill->lines);
        $line = $bill->lines->first();
        $this->assertSame(BillLineKind::AgreementFee, $line->kind);
        $this->assertSame('2026-06-10', $line->occurred_on->toDateString());
        $this->assertSame('25% of R 480 000.00 received, under the contingency fee agreement', $line->description);
        $this->assertSame(12000000, $line->fee_cents);
        $this->assertTrue($line->feeAgreement->is($agreement));

        $this->assertSame(12000000, $bill->fees_cents);
        $this->assertSame(12000000, $bill->total_cents);
    }

    public function test_an_estate_fee_bill_says_it_is_a_share_of_the_estate_funds(): void
    {
        $bill = $this->draft(FeeAgreement::factory()->estate(3.5)->finalised(100000000)->create());

        $this->assertSame('3.5% of the estate funds of R 1 000 000.00, under the estate fee agreement', $bill->lines->first()->description);
        $this->assertSame(3500000, $bill->total_cents);
    }

    public function test_a_fee_bill_carries_no_drawing_fee_even_after_its_amount_is_changed(): void
    {
        $settings = app(BillingSettings::class);
        $settings->drawingFeePercent = 5;
        $settings->save();

        $bill = $this->draft(FeeAgreement::factory()->contingency(25)->finalised(48000000)->create());

        $this->assertSame(0, $bill->drawing_fee_cents);
        $this->assertSame(0, $bill->drawing_fee_percent_hundredths);

        app(ChangeBillLineAmount::class)->change($bill->lines->first(), 10000000, $this->attorney);

        $bill->refresh();
        $this->assertSame(0, $bill->drawing_fee_cents);
        $this->assertSame(10000000, $bill->total_cents);
    }

    public function test_only_a_finalised_agreement_with_a_fee_is_billed(): void
    {
        $this->assertSame(
            ['Finalise the agreement with the amount received before billing the fee.'],
            $this->refusal(fn () => $this->draft(FeeAgreement::factory()->contingency()->create())),
        );
        $this->assertSame(
            ['Finalise the agreement with the amount received before billing the fee.'],
            $this->refusal(fn () => $this->draft(FeeAgreement::factory()->contingency()->failed()->create())),
        );
        $this->assertSame(
            ['There is no fee to bill: the agreed share of the amount received comes to nothing.'],
            $this->refusal(fn () => $this->draft(FeeAgreement::factory()->contingency()->finalised(0)->create())),
        );
        $this->assertSame(0, Bill::count());
    }

    public function test_an_agreement_is_billed_once_while_its_bill_is_live(): void
    {
        $agreement = FeeAgreement::factory()->contingency()->finalised()->create();
        $draft = $this->draft($agreement);

        $this->assertSame(['This agreement already has a fee bill.'], $this->refusal(fn () => $this->draft($agreement)));

        app(IssueBill::class)->issue($draft, $this->attorney);

        $this->assertSame(['This agreement already has a fee bill.'], $this->refusal(fn () => $this->draft($agreement)));
        $this->assertSame(1, Bill::count());
    }

    public function test_an_agreement_is_billed_again_once_its_bill_is_voided_or_its_draft_deleted(): void
    {
        $agreement = FeeAgreement::factory()->contingency()->finalised()->create();

        app(DeleteDraftBill::class)->delete($this->draft($agreement));

        $issued = app(IssueBill::class)->issue($this->draft($agreement), $this->attorney);
        app(VoidBill::class)->void($issued, $this->attorney, 'Wrong addressee');

        $again = $this->draft($agreement);

        $this->assertTrue($again->isDraft());
        $this->assertTrue($issued->lines()->first()->feeAgreement->is($agreement));
    }

    public function test_work_is_not_added_to_a_fee_bill(): void
    {
        $bill = $this->draft(FeeAgreement::factory()->contingency()->finalised()->create());

        $this->assertSame(
            ['A fee bill charges the agreed share only. Work is billed by statement or bill of cost.'],
            $this->refusal(fn () => app(AddWorkToBill::class)->add($bill, ['x'], [], $this->attorney)),
        );
    }
}
