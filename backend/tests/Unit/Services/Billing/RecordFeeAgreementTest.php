<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Billing;

use App\Enums\Billing\FeeAgreementKind;
use App\Enums\Billing\FeeAgreementStatus;
use App\Models\Billing\FeeAgreement;
use App\Models\Matter\Matter;
use App\Models\User\User;
use App\Services\Billing\FeeAgreementRefused;
use App\Services\Billing\RecordFeeAgreement;
use App\Settings\BillingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecordFeeAgreementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_share_left_empty_is_the_firms_default_for_the_kind(): void
    {
        $settings = app(BillingSettings::class);
        $settings->contingencyPercent = 20;
        $settings->save();

        $agreement = app(RecordFeeAgreement::class)->record(Matter::factory()->create(), FeeAgreementKind::Contingency, User::factory()->create());

        $this->assertSame(2000, $agreement->percent_hundredths);
        $this->assertSame(FeeAgreementStatus::Active, $agreement->status);
    }

    public function test_it_keeps_an_agreed_share_and_who_recorded_it(): void
    {
        $user = User::factory()->create();

        $agreement = app(RecordFeeAgreement::class)->record(
            Matter::factory()->create(), FeeAgreementKind::Estate, $user, percent: 3.25, signedOn: Carbon::parse('2026-11-01'),
        );

        $this->assertSame(325, $agreement->percent_hundredths);
        $this->assertSame('2026-11-01', $agreement->signed_on->toDateString());
        $this->assertTrue($agreement->recordedBy->is($user));
    }

    public function test_a_mandate_carries_no_share(): void
    {
        $agreement = app(RecordFeeAgreement::class)->record(Matter::factory()->create(), FeeAgreementKind::Mandate, User::factory()->create(), percent: 25);

        $this->assertNull($agreement->percent_hundredths);
    }

    public function test_it_keeps_the_signed_copy(): void
    {
        Storage::fake(config('media-library.disk_name'));

        $agreement = app(RecordFeeAgreement::class)->record(
            Matter::factory()->create(), FeeAgreementKind::Mandate, User::factory()->create(),
            signedCopy: UploadedFile::fake()->create('mandate.pdf', 10, 'application/pdf'),
        );

        $this->assertSame('mandate.pdf', $agreement->signedCopy()->file_name);
    }

    public function test_a_matter_has_one_agreement_in_place_at_a_time(): void
    {
        $matter = Matter::factory()->create();
        FeeAgreement::factory()->contingency()->for($matter)->create();

        $this->expectException(FeeAgreementRefused::class);

        app(RecordFeeAgreement::class)->record($matter, FeeAgreementKind::Mandate, User::factory()->create());
    }

    public function test_a_new_agreement_can_follow_a_failed_one(): void
    {
        $matter = Matter::factory()->create();
        FeeAgreement::factory()->contingency()->failed()->for($matter)->create();

        $agreement = app(RecordFeeAgreement::class)->record($matter, FeeAgreementKind::Mandate, User::factory()->create());

        $this->assertTrue($agreement->isActive());
    }
}
