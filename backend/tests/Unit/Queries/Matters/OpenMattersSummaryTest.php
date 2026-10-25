<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\Matters;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Matter\Assignment;
use App\Models\Diary\DiaryEntry;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\User\User;
use App\Queries\Matters\OpenMattersSummary;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenMattersSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_someone_who_may_not_see_every_matter_counts_only_their_own_open_ones(): void
    {
        $attorney = $this->userWith(RoleEnum::Attorney);
        $own = Matter::factory()->create();
        MatterAssignment::factory()->for($own)->for($attorney)->capacity(Assignment::Responsible)->create();
        Matter::factory()->create();

        $this->assertSame(1, (new OpenMattersSummary)->for($attorney)['openMattersCount']);
    }

    public function test_closed_matters_are_left_out(): void
    {
        Matter::factory()->count(2)->create();
        Matter::factory()->closed()->create();

        $this->assertSame(2, (new OpenMattersSummary)->for($this->userWith(RoleEnum::Director))['openMattersCount']);
    }

    public function test_it_counts_this_weeks_intakes_and_those_with_no_one_on_them(): void
    {
        $director = $this->userWith(RoleEnum::Director);
        $staffed = Matter::factory()->create(['instructed_at' => now()->subDays(2)]);
        MatterAssignment::factory()->for($staffed)->for($director)->capacity(Assignment::Responsible)->create();
        Matter::factory()->create(['instructed_at' => now()->subDays(3)]);
        Matter::factory()->create(['instructed_at' => now()->subDays(10)]);

        $summary = (new OpenMattersSummary)->for($director);

        $this->assertSame(2, $summary['openedThisWeekCount']);
        $this->assertSame(2, $summary['newIntakesCount']);
        $this->assertSame(1, $summary['unassignedIntakesCount']);
    }

    public function test_the_matters_most_in_need_of_attention_come_first(): void
    {
        $onTrack = Matter::factory()->create(['prescribes_at' => now()->addYear()]);
        $soon = Matter::factory()->create(['prescribes_at' => now()->addDays(10)]);
        $prescribed = Matter::factory()->create(['prescribes_at' => now()->subDay()]);
        $overdue = Matter::factory()->create(['prescribes_at' => now()->addYear()]);
        DiaryEntry::factory()->for($overdue)->overdue()->create();

        $rows = (new OpenMattersSummary)->for($this->userWith(RoleEnum::Director))['mattersNeedingAttention'];

        $this->assertEqualsCanonicalizing([$prescribed->id, $overdue->id], $rows->take(2)->map(fn (array $row) => $row['matter']->id)->all());
        $this->assertSame([$soon->id, $onTrack->id], $rows->slice(2)->map(fn (array $row) => $row['matter']->id)->values()->all());
        $this->assertSame(
            ['Prescribing soon' => 'warning', 'On track' => 'success'],
            $rows->slice(2)->mapWithKeys(fn (array $row) => [$row['label'] => $row['tone']])->all(),
        );
        $this->assertSame(['danger'], $rows->take(2)->pluck('tone')->unique()->values()->all());
    }

    public function test_it_shows_at_most_five_matters_needing_attention(): void
    {
        Matter::factory()->count(7)->create();

        $this->assertCount(5, (new OpenMattersSummary)->for($this->userWith(RoleEnum::Director))['mattersNeedingAttention']);
    }

    public function test_each_matter_carries_its_next_pending_diary_date(): void
    {
        $matter = Matter::factory()->create();
        $next = DiaryEntry::factory()->for($matter)->dueIn(2)->create();
        DiaryEntry::factory()->for($matter)->dueIn(9)->create();
        DiaryEntry::factory()->for($matter)->completed()->create(['due_at' => now()->addDay()]);

        $row = (new OpenMattersSummary)->for($this->userWith(RoleEnum::Director))['mattersNeedingAttention']->first();

        $this->assertTrue($next->due_at->equalTo($row['nextDate']));
    }

    protected function userWith(RoleEnum $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }
}
