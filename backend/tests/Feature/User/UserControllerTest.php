<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\Enums\Auth\Permission;
use App\Enums\Auth\Role as RoleEnum;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
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

    public function test_the_page_is_gated_behind_view_users(): void
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
            ->assertSee('l.'.str_repeat('·', 6).'@example.co.za')
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

    public function test_the_firm_routes_are_gated_by_permission_before_they_reach_a_controller(): void
    {
        $secretary = $this->userWithRole(RoleEnum::Secretary);
        $role = Role::findByName('messenger');
        $user = $this->userWithRole(RoleEnum::Attorney);

        $this->actingAs($secretary)->get(route('users'))->assertForbidden();
        $this->actingAs($secretary)->patch(route('user.details.update', $user), ['name' => 'X'])->assertForbidden();
        $this->actingAs($secretary)->post(route('invitation.store'), ['name' => 'A', 'email' => 'a@example.com', 'lifetime_days' => 7])->assertForbidden();
        $this->actingAs($secretary)->patch(route('user.roles.update', $user), ['roles' => []])->assertForbidden();
        $this->actingAs($secretary)->post(route('role.store'), ['name' => 'x'])->assertForbidden();
        $this->actingAs($secretary)->patch(route('role.update', $role), ['name' => 'x'])->assertForbidden();
        $this->actingAs($secretary)->delete(route('role.destroy', $role))->assertForbidden();
        $this->actingAs($secretary)->get(route('audit-log'))->assertForbidden();
        $this->actingAs($secretary)->get(route('audit-log.export'))->assertForbidden();
    }

    public function test_the_sidebar_only_links_to_what_the_user_may_open(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('href="'.route('users').'"', false)
            ->assertDontSee('href="'.route('audit-log').'"', false)
            ->assertDontSee('>Firm<', false);

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('dashboard'))
            ->assertSee('href="'.route('users').'"', false)
            ->assertSee('href="'.route('audit-log').'"', false);
    }

    public function test_roles_are_assigned_when_their_ids_arrive_as_strings_as_they_do_from_a_form(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);
        $clerk = $this->userWithRole(RoleEnum::Secretary);
        $custom = Role::create(['name' => 'paralegal', 'guard_name' => 'web']);
        $attorney = $this->roleId(RoleEnum::Attorney);

        // A browser submits every value as text: roles[]=7&roles[]=3.
        $this->actingAs($director)
            ->call('PATCH', route('user.roles.update', $clerk), ['roles' => [(string) $custom->getKey(), (string) $attorney]])
            ->assertRedirect(route('users'))
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(['paralegal', 'attorney'], $clerk->fresh()->getRoleNames()->all());
    }

    public function test_a_role_made_in_the_roles_sheet_can_then_be_given_to_a_user(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);
        $clerk = $this->userWithRole(RoleEnum::Secretary);

        $this->actingAs($director)
            ->call('POST', route('role.store'), ['name' => 'paralegal', 'permissions' => ['view_clients']])
            ->assertRedirect(route('users'));

        $role = Role::findByName('paralegal');

        $this->actingAs($director)
            ->call('PATCH', route('user.roles.update', $clerk), ['roles' => [(string) $role->getKey()]])
            ->assertSessionHasNoErrors();

        $this->assertSame(['paralegal'], $clerk->fresh()->getRoleNames()->all());
        $this->assertTrue($clerk->fresh()->can('view_clients'));
    }

    // ---- each power on its own ----

    protected function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    public function test_someone_who_may_only_view_users_sees_the_page_and_no_way_to_act_on_it(): void
    {
        $viewer = $this->userWith('view_users');
        $person = $this->userWithRole(RoleEnum::Attorney);

        $html = $this->actingAs($viewer)->get(route('users'))->assertOk()->assertSee('Permission matrix')->getContent();

        foreach (['Invite user', 'sheet-open\', \'roles\'', 'edit-user-', 'user-roles-'] as $control) {
            $this->assertStringNotContainsString($control, $html);
        }

        $this->actingAs($viewer)->patch(route('user.details.update', $person), ['name' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->post(route('invitation.store'), ['name' => 'A', 'email' => 'a@example.com', 'lifetime_days' => 7])->assertForbidden();
        $this->actingAs($viewer)->patch(route('user.roles.update', $person), ['roles' => []])->assertForbidden();
        $this->actingAs($viewer)->post(route('role.store'), ['name' => 'x'])->assertForbidden();
    }

    public function test_the_users_page_needs_view_users_not_the_other_powers(): void
    {
        foreach (['edit_users', 'invite_users', 'assign_roles', 'manage_roles'] as $permission) {
            $this->actingAs($this->userWith($permission))->get(route('users'))->assertForbidden();
        }
    }

    public function test_editing_a_staff_record_is_its_own_power(): void
    {
        $editor = $this->userWith('view_users', 'edit_users');
        $person = $this->userWithRole(RoleEnum::Attorney);

        $this->actingAs($editor)->patch(route('user.details.update', $person), ['name' => 'Renamed'])->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $person->fresh()->name);

        $this->actingAs($editor)->get(route('users'))
            ->assertSee('edit-user-'.$person->getKey())
            ->assertDontSee('Invite user')
            ->assertDontSee('user-roles-'.$person->getKey());

        $this->actingAs($editor)->patch(route('user.roles.update', $person), ['roles' => []])->assertForbidden();
    }

    public function test_inviting_is_its_own_power(): void
    {
        $inviter = $this->userWith('view_users', 'invite_users');

        $this->actingAs($inviter)->get(route('users'))
            ->assertSee('Invite user')
            ->assertDontSee('edit-user-');

        $this->actingAs($inviter)
            ->patch(route('user.details.update', $this->userWithRole(RoleEnum::Attorney)), ['name' => 'X'])
            ->assertForbidden();
    }

    public function test_giving_people_roles_is_separate_from_shaping_what_a_role_is(): void
    {
        $this->userWithRole(RoleEnum::Director); // somebody always holds the whole set
        $assigner = $this->userWith('view_users', 'assign_roles');
        $person = $this->userWithRole(RoleEnum::Secretary);
        $attorney = $this->roleId(RoleEnum::Attorney);

        $this->actingAs($assigner)
            ->patch(route('user.roles.update', $person), ['roles' => [$attorney]])
            ->assertSessionHasNoErrors();

        $this->assertSame(['attorney'], $person->fresh()->getRoleNames()->all());

        // The roles that exist can be handed out, but not rewritten.
        $this->actingAs($assigner)->post(route('role.store'), ['name' => 'power', 'permissions' => ['view_clients']])->assertForbidden();
        $this->actingAs($assigner)->patch(route('role.update', Role::findByName('attorney')), ['name' => 'attorney', 'permissions' => []])->assertForbidden();
        $this->actingAs($assigner)->delete(route('role.destroy', Role::findByName('messenger')))->assertForbidden();

        $this->actingAs($assigner)->get(route('users'))
            ->assertSee('user-roles-'.$person->getKey())
            ->assertDontSee('edit-role-');
    }

    public function test_shaping_roles_is_separate_from_giving_them_to_people(): void
    {
        $this->userWithRole(RoleEnum::Director); // somebody always holds the whole set
        $shaper = $this->userWith('view_users', 'manage_roles');
        $person = $this->userWithRole(RoleEnum::Secretary);

        $this->actingAs($shaper)->post(route('role.store'), ['name' => 'paralegal', 'permissions' => ['view_clients']])->assertSessionHasNoErrors();
        $this->assertNotNull(Role::query()->where('name', 'paralegal')->first());

        $this->actingAs($shaper)
            ->patch(route('user.roles.update', $person), ['roles' => [$this->roleId(RoleEnum::Director)]])
            ->assertForbidden();

        $this->actingAs($shaper)->get(route('users'))
            ->assertSee('edit-role-')
            ->assertDontSee('user-roles-'.$person->getKey());
    }

    public function test_the_guard_keeps_somebody_able_to_assign_roles_and_to_shape_them(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);
        $role = Role::findByName(RoleEnum::Director->value);

        // Taking the power to shape roles from the only role that has it would strand the firm.
        $this->actingAs($director)
            ->patch(route('role.update', $role), ['name' => 'director', 'permissions' => ['view_users', 'edit_users', 'invite_users', 'assign_roles']])
            ->assertSessionHasErrorsIn('role_'.$role->getKey(), 'permissions');

        $this->assertTrue($role->fresh()->hasPermissionTo('manage_roles'));
    }

    public function test_the_manager_and_director_hold_the_whole_set_by_default(): void
    {
        $set = ['view_users', 'edit_users', 'invite_users', 'assign_roles', 'manage_roles'];

        foreach ([RoleEnum::Director, RoleEnum::Manager] as $role) {
            $held = Role::findByName($role->value)->permissions->pluck('name')->all();

            $this->assertEmpty(array_diff($set, $held), "{$role->value} is missing part of the set");
        }
    }

    public function test_the_old_all_in_one_permission_is_gone(): void
    {
        $this->assertNull(\Spatie\Permission\Models\Permission::query()->where('name', 'manage_users')->first());
    }

    public function test_the_permission_matrix_has_room_to_breathe(): void
    {
        $this->actingAs($this->userWith('view_users'))
            ->get(route('users'))
            ->assertSee('--tblr-offcanvas-width: min(1180px, 96vw);', false);
    }
}
