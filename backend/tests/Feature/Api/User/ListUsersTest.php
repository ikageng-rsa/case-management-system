<?php

declare(strict_types=1);

namespace Tests\Feature\Api\User;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_needs_a_token(): void
    {
        $this->getJson('/api/v1/users')->assertUnauthorized();
    }

    public function test_others_are_shown_by_name_and_title_only(): void
    {
        $me = User::factory()->create(['name' => 'Ayanda Mokoena']);
        User::factory()->create(['name' => 'Thandi Baloyi', 'job_title' => 'Candidate attorney']);
        Sanctum::actingAs($me);

        $other = $this->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonPath('data.1.type', 'users')
            ->assertJsonPath('data.1.attributes.name', 'Thandi Baloyi')
            ->json('data.1.attributes');

        $this->assertSame(['name' => 'Thandi Baloyi', 'job_title' => 'Candidate attorney'], $other);
    }

    public function test_deactivated_people_are_left_out(): void
    {
        Sanctum::actingAs(User::factory()->create(['name' => 'Ayanda Mokoena']));
        User::factory()->create(['name' => 'Left the firm'])->delete();

        $this->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_it_finds_people_by_name(): void
    {
        Sanctum::actingAs(User::factory()->create(['name' => 'Ayanda Mokoena']));
        User::factory()->create(['name' => 'Thandi Baloyi']);

        $this->getJson('/api/v1/users?q=thandi')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.name', 'Thandi Baloyi');
    }

    public function test_the_list_comes_in_pages(): void
    {
        Sanctum::actingAs(User::factory()->create());
        User::factory()->count(30)->create();

        $this->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 31)
            ->assertJsonStructure(['links' => ['next']]);
    }
}
