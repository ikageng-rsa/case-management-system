<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Billing\FeeAgreementKind;
use App\Enums\Billing\FeeAgreementStatus;
use App\Enums\Matter\Assignment;
use App\Models\Billing\FeeAgreement;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\Reference\MatterType;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FeeAgreementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
        Storage::fake(config('media-library.disk_name'));
    }

    protected function userWithRole(RoleEnum $role): User
    {
        return tap(User::factory()->create())->assignRole($role->value);
    }

    protected function rafMatter(): Matter
    {
        return Matter::factory()->for(MatterType::factory()->create(['code' => 'RAF', 'default_fee_kind' => FeeAgreementKind::Contingency]))->create();
    }

    public function test_a_contingency_claim_is_recorded_then_fails_and_the_client_moves_to_a_mandate(): void
    {
        $secretary = $this->userWithRole(RoleEnum::Secretary);
        $matter = $this->rafMatter();

        $this->actingAs($secretary)->get(route('matter.show', [$matter, 'tab' => 'billing']))
            ->assertOk()
            ->assertSee('usually charged under contingency at 25%');

        $this->actingAs($secretary)->post(route('matter.fee-agreements.store', $matter), [
            'kind' => 'contingency',
            'signed_on' => '2026-11-01',
            'signed_copy' => UploadedFile::fake()->create('agreement.pdf', 10, 'application/pdf'),
        ])->assertRedirect(route('matter.show', [$matter, 'tab' => 'billing']));

        $contingency = $matter->feeAgreements()->sole();
        $this->assertSame(2500, $contingency->percent_hundredths);

        $this->actingAs($secretary)->get(route('matter.show', [$matter, 'tab' => 'billing']))->assertSee('Contingency, 25%');
        $this->actingAs($secretary)->get(route('fee-agreement.signed-copy', $contingency))->assertOk();

        $this->actingAs($secretary)->post(route('fee-agreement.fail', $contingency))->assertSessionHasNoErrors();
        $this->actingAs($secretary)->post(route('matter.fee-agreements.store', $matter), ['kind' => 'mandate'])->assertSessionHasNoErrors();

        $this->assertSame(FeeAgreementStatus::Failed, $contingency->fresh()->status);
        $this->assertSame(FeeAgreementKind::Mandate, $matter->feeAgreements()->active()->sole()->kind);
    }

    public function test_a_finalised_claim_shows_what_was_received_and_the_fee_to_those_who_see_bills(): void
    {
        $attorney = $this->userWithRole(RoleEnum::Attorney);
        $matter = $this->rafMatter();
        MatterAssignment::factory()->for($matter)->for($attorney)->create(['capacity' => Assignment::Responsible]);
        $agreement = FeeAgreement::factory()->contingency(25)->for($matter)->create();

        $this->actingAs($attorney)->post(route('fee-agreement.finalise', $agreement), ['amount_received' => '480000.00'])
            ->assertSessionHasNoErrors();

        // The attorney who finalised it sees that it is finalised, but amounts are for those who may see bills.
        $this->actingAs($attorney)->get(route('matter.show', [$matter, 'tab' => 'billing']))
            ->assertSee('Finalised')
            ->assertDontSee('R 480 000.00');

        $this->actingAs($this->userWithRole(RoleEnum::Director))->get(route('matter.show', [$matter, 'tab' => 'billing']))
            ->assertSee('received R 480 000.00, fee R 120 000.00');
    }

    public function test_a_second_agreement_is_refused_while_one_is_in_place(): void
    {
        $matter = $this->rafMatter();
        FeeAgreement::factory()->contingency()->for($matter)->create();

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->post(route('matter.fee-agreements.store', $matter), ['kind' => 'mandate'])
            ->assertSessionHasErrorsIn('fee_agreement', 'kind');

        $this->assertSame(1, $matter->feeAgreements()->count());
    }

    public function test_billing_needs_the_permission_and_sight_of_the_matter(): void
    {
        $matter = $this->rafMatter();
        $agreement = FeeAgreement::factory()->contingency()->for($matter)->create();
        $candidate = $this->userWithRole(RoleEnum::CandidateAttorney);
        MatterAssignment::factory()->for($matter)->for($candidate)->create();

        // Assigned, but without the permission: no billing tab and no changes.
        $this->actingAs($candidate)->get(route('matter.show', [$matter, 'tab' => 'billing']))->assertViewHas('tab', 'ledger');
        $this->actingAs($candidate)->post(route('fee-agreement.fail', $agreement))->assertForbidden();

        // The permission, but not on this matter.
        $attorney = $this->userWithRole(RoleEnum::Attorney);
        $this->actingAs($attorney)->post(route('matter.fee-agreements.store', $matter), ['kind' => 'mandate'])->assertForbidden();
    }

    public function test_a_matter_type_carries_the_agreement_its_matters_start_from(): void
    {
        $type = MatterType::factory()->create(['code' => 'EST']);

        $this->actingAs($this->userWithRole(RoleEnum::Manager))
            ->patch(route('matter-type.update', $type), ['name' => $type->name, 'default_fee_kind' => 'estate'])
            ->assertSessionHasNoErrors();

        $this->assertSame(FeeAgreementKind::Estate, $type->fresh()->default_fee_kind);
    }
}
