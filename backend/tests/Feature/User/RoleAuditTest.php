<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\Enums\Auth\Role as RoleEnum;
use App\Models\User\Role;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class RoleAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
        Activity::query()->delete();
    }

    protected function director(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleEnum::Director->value);
        Activity::query()->delete();

        return $user;
    }

    protected function events(Role|User $subject, string $event)
    {
        return Activity::forSubject($subject)->where('event', $event)->get();
    }

    public function test_creating_a_role_is_logged_with_who_and_what_it_may_do(): void
    {
        $director = $this->director();

        $this->actingAs($director)->post(route('role.store'), ['name' => 'paralegal', 'permissions' => ['view_clients', 'create_matters']]);

        $role = Role::findByName('paralegal');

        $created = $this->events($role, 'created');
        $this->assertCount(1, $created);
        $this->assertTrue($created->first()->causer->is($director));

        $changed = $this->events($role, 'permissions_changed');
        $this->assertCount(1, $changed);
        $this->assertEqualsCanonicalizing(['view_clients', 'create_matters'], $changed->first()->properties['granted']);
        $this->assertSame([], $changed->first()->properties['revoked']);
    }

    public function test_renaming_a_role_is_logged_with_the_old_and_new_name(): void
    {
        $director = $this->director();
        $role = Role::create(['name' => 'clerk', 'guard_name' => 'web']);
        Activity::query()->delete();

        $this->actingAs($director)->patch(route('role.update', $role), ['name' => 'office_clerk', 'permissions' => []]);

        $updated = $this->events($role, 'updated');
        $this->assertCount(1, $updated);
        $this->assertSame('office_clerk', $updated->first()->attribute_changes['attributes']['name']);
        $this->assertSame('clerk', $updated->first()->attribute_changes['old']['name']);
    }

    public function test_changing_what_a_role_may_do_logs_only_the_difference(): void
    {
        $director = $this->director();
        $role = Role::create(['name' => 'paralegal', 'guard_name' => 'web']);
        $role->syncPermissions(['view_clients', 'create_matters', 'edit_matters']);
        Activity::query()->delete();

        $this->actingAs($director)->patch(route('role.update', $role), ['name' => 'paralegal', 'permissions' => ['view_clients', 'create_matters', 'close_matters']]);

        $changed = $this->events($role, 'permissions_changed');

        $this->assertCount(1, $changed);
        $this->assertSame(['close_matters'], $changed->first()->properties['granted']);
        $this->assertSame(['edit_matters'], $changed->first()->properties['revoked']);
    }

    public function test_saving_a_role_unchanged_logs_nothing(): void
    {
        $director = $this->director();
        $role = Role::create(['name' => 'paralegal', 'guard_name' => 'web']);
        $role->syncPermissions(['view_clients']);
        Activity::query()->delete();

        $this->actingAs($director)->patch(route('role.update', $role), ['name' => 'paralegal', 'permissions' => ['view_clients']]);

        $this->assertSame(0, Activity::forSubject($role)->count());
    }

    public function test_deleting_a_role_is_logged(): void
    {
        $director = $this->director();
        $role = Role::create(['name' => 'temp', 'guard_name' => 'web']);
        Activity::query()->delete();

        $this->actingAs($director)->delete(route('role.destroy', $role));

        $this->assertCount(1, $this->events($role, 'deleted'));
    }

    public function test_keeping_a_role_while_adding_another_does_not_log_the_kept_one_as_revoked(): void
    {
        $director = $this->director();
        $person = User::factory()->create();
        $person->assignRole(RoleEnum::Attorney->value);
        Activity::query()->delete();

        $this->actingAs($director)->call('PATCH', route('user.roles.update', $person), [
            'roles' => [
                (string) Role::findByName('attorney')->getKey(),
                (string) Role::findByName('legal_secretary')->getKey(),
            ],
        ])->assertSessionHasNoErrors();

        $this->assertCount(1, $this->events($person, 'role_granted'));
        $this->assertSame('legal_secretary', $this->events($person, 'role_granted')->first()->properties['role']);
        $this->assertCount(0, $this->events($person, 'role_revoked'), 'a role that was kept is not revoked');
        $this->assertEqualsCanonicalizing(['attorney', 'legal_secretary'], $person->fresh()->getRoleNames()->all());
    }

    public function test_dropping_a_role_logs_only_that_one_as_revoked(): void
    {
        $director = $this->director();
        $person = User::factory()->create();
        $person->assignRole([RoleEnum::Attorney->value, RoleEnum::Secretary->value]);
        Activity::query()->delete();

        $this->actingAs($director)->call('PATCH', route('user.roles.update', $person), [
            'roles' => [(string) Role::findByName('attorney')->getKey()],
        ]);

        $this->assertCount(0, $this->events($person, 'role_granted'));
        $this->assertCount(1, $this->events($person, 'role_revoked'));
        $this->assertSame('legal_secretary', $this->events($person, 'role_revoked')->first()->properties['role']);
    }

    public function test_the_audit_page_reads_a_permission_change_as_a_list(): void
    {
        $director = $this->director();
        $role = Role::create(['name' => 'paralegal', 'guard_name' => 'web']);
        Activity::query()->delete();

        $this->actingAs($director)->patch(route('role.update', $role), ['name' => 'paralegal', 'permissions' => ['view_clients', 'edit_clients']]);

        $details = $this->actingAs($director)->get(route('audit-log', ['action' => 'permissions_changed']))->viewData('details');

        $properties = collect($details->first()['properties'])->keyBy('key');

        $this->assertEqualsCanonicalizing(['view_clients', 'edit_clients'], explode(', ', $properties['granted']['value']));
        $this->assertSame('—', $properties['revoked']['value']);
    }
}
