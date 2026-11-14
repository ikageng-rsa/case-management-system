<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\Diary;

use App\Models\Diary\DiaryEntry;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\User\User;
use App\Queries\Diary\UserDiary;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDiaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_it_lists_only_the_users_own_entries_on_matters_they_carry(): void
    {
        $user = User::factory()->create();
        $matter = Matter::factory()->create();
        MatterAssignment::factory()->for($matter)->for($user)->create();
        $mine = DiaryEntry::factory()->for($matter)->for($user, 'assignee')->create();
        DiaryEntry::factory()->for($matter)->create();
        DiaryEntry::factory()->for(Matter::factory())->for($user, 'assignee')->create();

        $diary = (new UserDiary)->for($user, 'pending', null, null);

        $this->assertSame([$mine->id], $diary['entries']->pluck('id')->all());
        $this->assertSame(1, $diary['counts']['pending']);
    }

    public function test_completed_entries_come_most_recently_done_first(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_all_matters');
        $older = DiaryEntry::factory()->for($user, 'assignee')->completed()->create(['completed_at' => now()->subDays(2)]);
        $newer = DiaryEntry::factory()->for($user, 'assignee')->completed()->create(['completed_at' => now()->subHour()]);

        $diary = (new UserDiary)->for($user, 'completed', null, null);

        $this->assertSame([$newer->id, $older->id], $diary['entries']->pluck('id')->all());
    }
}
