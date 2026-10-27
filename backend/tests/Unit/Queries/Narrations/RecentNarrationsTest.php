<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\Narrations;

use App\Enums\Auth\Role as RoleEnum;
use App\Models\Narration\Narration;
use App\Models\User\User;
use App\Queries\Narrations\RecentNarrations;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecentNarrationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_it_lists_the_latest_four_newest_first(): void
    {
        $narrations = collect(range(5, 1))->map(
            fn (int $daysAgo) => Narration::factory()->occurredAt(now()->subDays($daysAgo))->create(),
        );

        $recent = (new RecentNarrations)->for($this->userWith(RoleEnum::Director));

        $this->assertSame($narrations->reverse()->take(4)->pluck('id')->values()->all(), $recent->pluck('id')->all());
    }

    public function test_someone_who_may_not_see_every_matter_sees_only_their_own(): void
    {
        $attorney = $this->userWith(RoleEnum::Attorney);
        $own = Narration::factory()->for($attorney, 'author')->create();
        Narration::factory()->create();

        $this->assertSame([$own->id], (new RecentNarrations)->for($attorney)->pluck('id')->all());
    }

    protected function userWith(RoleEnum $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }
}
