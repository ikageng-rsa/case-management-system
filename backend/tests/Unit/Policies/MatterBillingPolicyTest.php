<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\Auth\Permission;
use App\Enums\Auth\Role as RoleEnum;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatterBillingPolicyTest extends TestCase
{
    use RefreshDatabase;

    private const BILLING = ['manageFees', 'viewBills', 'draftBills', 'issueBills', 'voidBills', 'recordPayments', 'reversePayments'];

    protected Matter $matter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->matter = Matter::factory()->create();
    }

    protected function userWith(Permission ...$permissions): User
    {
        return tap(User::factory()->create())->givePermissionTo(array_column($permissions, 'value'));
    }

    /** @return list<string> the billing abilities the user has on the matter */
    protected function allowed(User $user, ?Matter $matter = null): array
    {
        return array_values(array_filter(self::BILLING, fn (string $ability) => $user->can($ability, $matter ?? $this->matter)));
    }

    public function test_finance_works_on_the_money_of_every_matter_without_seeing_its_file(): void
    {
        $finance = tap(User::factory()->create())->assignRole(RoleEnum::Finance->value);

        $this->assertFalse($finance->can('view', $this->matter));
        $this->assertSame(['manageFees', 'viewBills', 'draftBills', 'issueBills', 'recordPayments'], $this->allowed($finance));
    }

    public function test_seeing_a_file_brings_none_of_its_money(): void
    {
        $candidate = tap(User::factory()->create())->assignRole(RoleEnum::CandidateAttorney->value);
        MatterAssignment::factory()->for($this->matter)->for($candidate)->create();

        $this->assertTrue($candidate->can('view', $this->matter));
        $this->assertSame([], $this->allowed($candidate));
    }

    public function test_an_attorney_records_how_the_client_is_charged_but_sees_no_amounts(): void
    {
        $attorney = tap(User::factory()->create())->assignRole(RoleEnum::Attorney->value);
        MatterAssignment::factory()->for($this->matter)->for($attorney)->create();

        $this->assertSame(['manageFees'], $this->allowed($attorney));
        $this->assertSame([], $this->allowed($attorney, Matter::factory()->create()));
    }

    public function test_a_billing_permission_reaches_only_the_matters_a_person_sees_unless_they_work_on_all_billing(): void
    {
        $biller = $this->userWith(Permission::ViewBills, Permission::DraftBills);
        MatterAssignment::factory()->for($this->matter)->for($biller)->create();
        $other = Matter::factory()->create();

        $this->assertSame(['viewBills', 'draftBills'], $this->allowed($biller));
        $this->assertSame([], $this->allowed($biller, $other));

        $biller->givePermissionTo(Permission::AccessAllBilling->value);

        $this->assertSame(['viewBills', 'draftBills'], $this->allowed($biller->fresh(), $other));
    }

    public function test_each_billing_ability_is_its_own_permission(): void
    {
        // Working on all billing reaches every matter but, by itself, allows nothing on any of them.
        $this->assertSame([], $this->allowed($this->userWith(Permission::AccessAllBilling)));

        // Acting on a bill does not show amounts, and recording a payment does not allow reversing one.
        $this->assertSame(['recordPayments'], $this->allowed($this->userWith(Permission::AccessAllBilling, Permission::RecordPayments)));
        $this->assertSame(['reversePayments'], $this->allowed($this->userWith(Permission::AccessAllBilling, Permission::ReversePayments)));
    }

    public function test_the_director_has_every_billing_ability(): void
    {
        $director = tap(User::factory()->create())->assignRole(RoleEnum::Director->value);

        $this->assertSame(self::BILLING, $this->allowed($director));
    }
}
