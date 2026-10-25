<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\Auth\Role;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Assert as PHPUnit;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        // One error of the given status in the response, pointing at the field when one is named.
        TestResponse::macro('assertJsonApiError', function (int $status, ?string $pointer = null, ?string $detail = null): TestResponse {
            /** @var TestResponse $this */
            $this->assertStatus($status)->assertHeader('Content-Type', 'application/vnd.api+json');

            $matching = collect($this->json('errors'))
                ->filter(fn (array $error) => $error['status'] === (string) $status)
                ->filter(fn (array $error) => $pointer === null || ($error['source']['pointer'] ?? null) === $pointer)
                ->filter(fn (array $error) => $detail === null || ($error['detail'] ?? null) === $detail);

            PHPUnit::assertNotEmpty($matching->all(), 'No '.$status.' error'.($pointer ? " at {$pointer}" : '').' in '.$this->getContent());

            return $this;
        });
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
