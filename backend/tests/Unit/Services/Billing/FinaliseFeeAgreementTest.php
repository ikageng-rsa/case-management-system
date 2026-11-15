<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Billing;

use App\Enums\Billing\FeeAgreementStatus;
use App\Models\Billing\FeeAgreement;
use App\Services\Billing\FeeAgreementRefused;
use App\Services\Billing\FinaliseFeeAgreement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinaliseFeeAgreementTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_closes_the_agreement_with_what_was_received_and_the_fee_follows(): void
    {
        $agreement = FeeAgreement::factory()->contingency(25)->create();

        (new FinaliseFeeAgreement)->finalise($agreement, 48000000);

        $this->assertSame(FeeAgreementStatus::Finalised, $agreement->status);
        $this->assertNotNull($agreement->closed_at);
        $this->assertSame(12000000, $agreement->fee());
    }

    public function test_an_estate_fee_is_its_share_of_the_estate(): void
    {
        $agreement = FeeAgreement::factory()->estate(3.5)->create();

        (new FinaliseFeeAgreement)->finalise($agreement, 100000000);

        $this->assertSame(3500000, $agreement->fee());
    }

    public function test_a_mandate_is_not_finalised_with_an_amount(): void
    {
        $this->expectException(FeeAgreementRefused::class);

        (new FinaliseFeeAgreement)->finalise(FeeAgreement::factory()->create(), 100);
    }

    public function test_a_closed_agreement_is_not_finalised_again(): void
    {
        $this->expectException(FeeAgreementRefused::class);

        (new FinaliseFeeAgreement)->finalise(FeeAgreement::factory()->contingency()->failed()->create(), 100);
    }
}
