<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\Enums\Auth\Permission;
use App\Enums\Auth\Role as RoleEnum;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    protected function userWithRole(RoleEnum $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role->value);

        return $user;
    }

    protected function roleId(RoleEnum $role): int
    {
        return Role::findByName($role->value)->getKey();
    }

    public function test_the_page_is_gated_behind_manage_users(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Secretary))->get(route('users'))->assertForbidden();
        $this->actingAs($this->userWithRole(RoleEnum::Manager))->get(route('users'))->assertOk();
    }

    public function test_it_lists_users_with_their_roles_and_masked_emails(): void
    {
        $director = $this->userWithRole(RoleEnum::Director, ['name' => 'L. Radebe', 'email' => 'l.radebe@example.co.za']);
        $director->assignRole(RoleEnum::Attorney->value);

        $this->actingAs($director)
            ->get(route('users'))
            ->assertOk()
            ->assertSee('L. Radebe')
            ->assertSee('Director · Attorney')
            ->assertSee('l.radebe@'.str_repeat('·', 10).'.za')
            ->assertDontSee('l.radebe@example.co.za');
    }

    public function test_last_active_comes_from_the_session_store(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);
        $seen = $this->userWithRole(RoleEnum::Attorney);

        DB::table('sessions')->insert([
            'id' => 'abc', 'user_id' => $seen->getKey(), 'payload' => '', 'last_activity' => now()->subMinutes(18)->timestamp,
        ]);

        $this->actingAs($director)
            ->get(route('users'))
            ->assertViewHas('lastActive', fn ($active) => isset($active[$seen->getKey()]) && ! isset($active[$director->getKey()]))
            ->assertSee('18 minutes ago');
    }

    public function test_the_permission_matrix_is_built_from_the_roles_as_they_stand(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('users'))
            ->assertSee('Permission matrix')
            ->assertSee('View All Matters')
            ->assertSee('Candidate Attorney');
    }

    public function test_roles_can_be_assigned_to_a_user_and_the_change_is_recorded_once(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);
        $clerk = $this->userWithRole(RoleEnum::Secretary);

        $count = fn (string $event) => Activity::query()->where('event', $event)->where('subject_id', $clerk->getKey())->count();
        [$grantedBefore, $revokedBefore] = [$count('role_granted'), $count('role_revoked')];

        $this->actingAs($director)
            ->patch(route('user.roles.update', $clerk), ['roles' => [$this->roleId(RoleEnum::Attorney)]])
            ->assertRedirect(route('users'));

        $this->assertSame(['attorney'], $clerk->fresh()->getRoleNames()->all());
        $this->assertSame(1, $count('role_granted') - $grantedBefore);
        $this->assertSame(1, $count('role_revoked') - $revokedBefore);
    }

    public function test_assigning_roles_needs_the_permission(): void
    {
        $clerk = $this->userWithRole(RoleEnum::Secretary);

        $this->actingAs($this->userWithRole(RoleEnum::Attorney))
            ->patch(route('user.roles.update', $clerk), ['roles' => [$this->roleId(RoleEnum::Director)]])
            ->assertForbidden();

        $this->assertSame(['legal_secretary'], $clerk->fresh()->getRoleNames()->all());
    }

    public function test_a_role_that_does_not_exist_cannot_be_assigned(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);

        $this->actingAs($director)
            ->patch(route('user.roles.update', $director), ['roles' => [9999]])
            ->assertSessionHasErrorsIn('user_roles_'.$director->getKey(), 'roles.0');
    }

    public function test_the_last_person_able_to_administer_cannot_remove_their_own_roles(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);

        $this->actingAs($director)
            ->patch(route('user.roles.update', $director), ['roles' => []])
            ->assertSessionHasErrorsIn('user_roles_'.$director->getKey(), 'roles');

        $this->assertSame(['director'], $director->fresh()->getRoleNames()->all());
    }

    public function test_a_role_can_be_created_from_the_permissions_the_system_defines(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->post(route('role.store'), [
                'name' => 'paralegal',
                'permissions' => [Permission::ViewClients->value, Permission::CreateMatters->value],
            ])
            ->assertRedirect(route('users'));

        $role = Role::findByName('paralegal');

        $this->assertEqualsCanonicalizing(['view_clients', 'create_matters'], $role->permissions->pluck('name')->all());
    }

    public function test_a_permission_cannot_be_invented_and_a_name_cannot_be_reused(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);

        $this->actingAs($director)
            ->post(route('role.store'), ['name' => 'paralegal', 'permissions' => ['do_anything']])
            ->assertSessionHasErrorsIn('role_new', 'permissions.0');

        $this->actingAs($director)
            ->post(route('role.store'), ['name' => 'attorney'])
            ->assertSessionHasErrorsIn('role_new', 'name');

        $this->assertNull(Role::query()->where('name', 'paralegal')->first());
    }

    public function test_a_role_can_be_renamed_and_regranted_and_keeps_its_users(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);
        $clerk = $this->userWithRole(RoleEnum::Secretary);
        $role = Role::findByName(RoleEnum::Secretary->value);

        $this->actingAs($director)
            ->patch(route('role.update', $role), ['name' => 'front_office', 'permissions' => [Permission::ViewClients->value]])
            ->assertRedirect(route('users'));

        $this->assertSame(['front_office'], $clerk->fresh()->getRoleNames()->all());
        $this->assertSame(['view_clients'], $role->fresh()->permissions->pluck('name')->all());
    }

    public function test_a_role_can_keep_its_own_name_when_edited(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);
        $role = Role::findByName(RoleEnum::Secretary->value);

        $this->actingAs($director)
            ->patch(route('role.update', $role), ['name' => 'legal_secretary', 'permissions' => [Permission::ViewClients->value]])
            ->assertSessionHasNoErrors();
    }

    public function test_editing_a_role_cannot_leave_nobody_able_to_administer(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);
        $role = Role::findByName(RoleEnum::Director->value);

        $this->actingAs($director)
            ->patch(route('role.update', $role), ['name' => 'director', 'permissions' => [Permission::ViewClients->value]])
            ->assertSessionHasErrorsIn('role_'.$role->getKey(), 'permissions');

        $this->assertTrue($role->fresh()->hasPermissionTo(Permission::AssignRoles->value));
    }

    public function test_a_role_in_use_cannot_be_deleted_but_an_unused_one_can(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);
        $this->userWithRole(RoleEnum::Secretary);

        $this->actingAs($director)
            ->delete(route('role.destroy', Role::findByName(RoleEnum::Secretary->value)))
            ->assertSessionHasErrorsIn('roles', 'role');

        $this->assertNotNull(Role::query()->where('name', 'legal_secretary')->first());

        $this->actingAs($director)
            ->delete(route('role.destroy', Role::findByName(RoleEnum::Messenger->value)))
            ->assertRedirect(route('users'));

        $this->assertNull(Role::query()->where('name', 'messenger')->first());
    }

    public function test_managing_roles_needs_the_permission(): void
    {
        $attorney = $this->userWithRole(RoleEnum::Attorney);

        $this->actingAs($attorney)->post(route('role.store'), ['name' => 'x'])->assertForbidden();
        $this->actingAs($attorney)->patch(route('role.update', Role::findByName('messenger')), ['name' => 'x'])->assertForbidden();
        $this->actingAs($attorney)->delete(route('role.destroy', Role::findByName('messenger')))->assertForbidden();
    }
}
