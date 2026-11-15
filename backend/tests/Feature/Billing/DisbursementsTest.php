<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Billing\FirmAccount;
use App\Models\Billing\Disbursement;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DisbursementsTest extends TestCase
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

    public function test_a_disbursement_is_recorded_with_its_receipt_and_shown_with_the_total(): void
    {
        $secretary = $this->userWithRole(RoleEnum::Secretary);
        $matter = Matter::factory()->create();
        Disbursement::factory()->for($matter)->create(['amount_cents' => 45000]);

        $this->actingAs($secretary)->post(route('matter.disbursements.store', $matter), [
            'incurred_on' => '2026-11-10',
            'description' => 'Medical expert report',
            'amount' => '20000.00',
            'paid_from' => 'trust',
            'receipt' => UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf'),
        ])->assertRedirect(route('matter.show', [$matter, 'tab' => 'billing']));

        $disbursement = $matter->disbursements()->latest('id')->first();
        $this->assertSame(2000000, $disbursement->amount_cents);
        $this->assertSame(FirmAccount::Trust, $disbursement->paid_from);

        $this->actingAs($secretary)->get(route('matter.show', [$matter, 'tab' => 'billing']))
            ->assertOk()
            ->assertSee('R 20 450.00 paid out')
            ->assertSee('Medical expert report')
            ->assertSee('Trust account');

        $this->actingAs($secretary)->get(route('disbursement.receipt', $disbursement))->assertOk();
    }

    public function test_an_amount_and_a_description_are_needed(): void
    {
        $matter = Matter::factory()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->post(route('matter.disbursements.store', $matter), ['incurred_on' => '2026-11-10', 'amount' => '0', 'paid_from' => 'business'])
            ->assertSessionHasErrorsIn('disbursement', ['description', 'amount']);

        $this->assertSame(0, $matter->disbursements()->count());
    }

    public function test_one_recorded_in_error_is_removed(): void
    {
        $disbursement = Disbursement::factory()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->delete(route('disbursement.destroy', $disbursement))
            ->assertRedirect(route('matter.show', [$disbursement->matter, 'tab' => 'billing']));

        $this->assertModelMissing($disbursement);
    }

    public function test_disbursements_need_the_fee_permission_and_sight_of_the_matter(): void
    {
        $disbursement = Disbursement::factory()->create();
        $candidate = $this->userWithRole(RoleEnum::CandidateAttorney);
        MatterAssignment::factory()->for($disbursement->matter)->for($candidate)->create();
        $attorney = $this->userWithRole(RoleEnum::Attorney);

        $valid = ['incurred_on' => '2026-11-10', 'description' => 'Filing fee', 'amount' => '50', 'paid_from' => 'business'];

        $this->actingAs($candidate)->post(route('matter.disbursements.store', $disbursement->matter), $valid)->assertForbidden();
        $this->actingAs($candidate)->delete(route('disbursement.destroy', $disbursement))->assertForbidden();
        $this->actingAs($attorney)->get(route('disbursement.receipt', $disbursement))->assertForbidden();
    }
}
