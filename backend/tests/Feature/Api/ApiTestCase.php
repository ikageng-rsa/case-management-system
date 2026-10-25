<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\Auth\Role;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    /** Someone holding the role, signed in on a phone. */
    protected function signInAs(Role $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        Sanctum::actingAs($user);

        return $user;
    }
}
