<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Billing;

use App\Enums\Billing\FeeAgreementStatus;
use App\Models\Billing\FeeAgreement;
use App\Services\Billing\FeeAgreementRefused;
use App\Services\Billing\MarkFeeAgreementFailed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkFeeAgreementFailedTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_contingency_claim_is_closed_as_failed_with_no_fee(): void
    {
        $agreement = FeeAgreement::factory()->contingency()->create();

        (new MarkFeeAgreementFailed)->fail($agreement);

        $this->assertSame(FeeAgreementStatus::Failed, $agreement->status);
        $this->assertNull($agreement->fee());
    }

    public function test_only_a_contingency_claim_can_fail(): void
    {
        $this->expectException(FeeAgreementRefused::class);

        (new MarkFeeAgreementFailed)->fail(FeeAgreement::factory()->estate()->create());
    }
}
