<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Billing\BillKind;
use App\Models\Billing\Bill;
use App\Models\Billing\FeeAgreement;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\Narration\Narration;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BillingWithoutTheFileTest extends TestCase
{
    use RefreshDatabase;

    protected User $finance;

    protected User $director;

    protected Matter $matter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
        Storage::fake(config('media-library.disk_name'));

        $this->finance = tap(User::factory()->create(['name' => 'Fikile Nkosi']))->assignRole(RoleEnum::Finance->value);
        $this->director = tap(User::factory()->create())->assignRole(RoleEnum::Director->value);
        $this->matter = Matter::factory()->create(['title' => 'Claim for damages']);
    }

    public function test_finance_finds_bills_on_every_matter_from_the_bills_page(): void
    {
        $owed = Bill::factory()->for($this->matter)->issued('INV/2026/0001')->create(['addressee' => 'Mrs A Client', 'total_cents' => 1000000, 'paid_cents' => 400000]);
        Bill::factory()->issued('INV/2026/0002')->create(['addressee' => 'Mr B Other', 'total_cents' => 500000, 'paid_cents' => 500000]);
        Bill::factory()->create(['addressee' => 'A draft bill', 'total_cents' => 300000]);

        $this->actingAs($this->finance)->get(route('bills'))
            ->assertOk()
            ->assertSee('INV/2026/0001')
            ->assertSee('INV/2026/0002')
            ->assertSee('A draft bill')
            ->assertSee($this->matter->reference)
            ->assertSee(route('billing.matter', $this->matter))
            ->assertDontSee(route('matter.show', $this->matter));

        $this->actingAs($this->finance)->get(route('bills', ['show' => 'owed']))
            ->assertOk()
            ->assertSee('INV/2026/0001')
            ->assertSee('R 6 000.00')
            ->assertDontSee('INV/2026/0002')
            ->assertDontSee('A draft bill');

        $this->actingAs($this->finance)->get(route('bills', ['q' => 'B Other']))
            ->assertOk()
            ->assertSee('INV/2026/0002')
            ->assertDontSee('INV/2026/0001');

        $this->actingAs($this->finance)->get(route('bills', ['q' => 'nothing like this']))
            ->assertOk()
            ->assertSee('No bills match that search.');

        // The bill leads back to the matter's billing, never into the file.
        $this->actingAs($this->finance)->get(route('bill.show', $owed))
            ->assertOk()
            ->assertSee(route('billing.matter', $this->matter))
            ->assertDontSee(route('matter.show', $this->matter));
    }

    public function test_finance_works_on_a_matters_billing_without_its_file(): void
    {
        Narration::factory()->for($this->matter)->create(['body' => 'Consulted with the client about the accident.']);
        $this->actingAs($this->finance)->get(route('matter.show', $this->matter))->assertForbidden();

        $this->actingAs($this->finance)->get(route('billing.matter', $this->matter))
            ->assertOk()
            ->assertSee($this->matter->reference)
            ->assertSee($this->matter->client->full_name)
            ->assertSee('Record fee agreement')
            ->assertSee('Raise a bill')
            ->assertDontSee('Consulted with the client about the accident.')
            ->assertDontSee('Ledger')
            ->assertDontSee('Diary');

        // Recording a fee agreement returns to the billing page, not to the matter.
        $this->actingAs($this->finance)->post(route('matter.fee-agreements.store', $this->matter), ['kind' => 'mandate', 'signed_on' => '2026-11-01'])
            ->assertRedirect(route('billing.matter', $this->matter));

        $this->assertSame(1, FeeAgreement::count());
    }

    public function test_someone_who_sees_the_file_is_sent_to_its_billing_tab(): void
    {
        $this->actingAs($this->director)->get(route('billing.matter', $this->matter))
            ->assertRedirect(route('matter.show', [$this->matter, 'tab' => 'billing']));

        $this->actingAs($this->director)->post(route('matter.fee-agreements.store', $this->matter), ['kind' => 'mandate', 'signed_on' => '2026-11-01'])
            ->assertRedirect(route('matter.show', [$this->matter, 'tab' => 'billing']));
    }

    public function test_the_bills_page_and_a_matters_billing_are_closed_to_people_without_billing_permissions(): void
    {
        $candidate = tap(User::factory()->create())->assignRole(RoleEnum::CandidateAttorney->value);
        MatterAssignment::factory()->for($this->matter)->for($candidate)->create();

        $this->actingAs($candidate)->get(route('bills'))->assertForbidden();
        $this->actingAs($candidate)->get(route('billing.matter', $this->matter))->assertForbidden();
        $this->actingAs($candidate)->get(route('dashboard'))->assertOk()->assertDontSee(route('bills'));

        $this->actingAs($this->finance)->get(route('dashboard'))->assertOk()->assertSee(route('bills'));
    }

    public function test_a_biller_without_all_billing_sees_only_bills_on_their_own_matters(): void
    {
        $biller = tap(User::factory()->create())->givePermissionTo(['view_bills', 'draft_bills']);
        MatterAssignment::factory()->for($this->matter)->for($biller)->create();
        Bill::factory()->for($this->matter)->issued('INV/2026/0001')->create();
        Bill::factory()->issued('INV/2026/0002')->create(['kind' => BillKind::BillOfCost]);

        $this->actingAs($biller)->get(route('bills'))
            ->assertOk()
            ->assertSee('INV/2026/0001')
            ->assertDontSee('INV/2026/0002');
    }
}
