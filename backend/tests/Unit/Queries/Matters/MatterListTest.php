<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\Matters;

use App\Enums\Auth\Permission;
use App\Enums\Matter\Assignment;
use App\Models\DiaryEntry;
use App\Models\Matter;
use App\Models\MatterAssignment;
use App\Models\MatterType;
use App\Models\User;
use App\Queries\Matters\MatterList;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatterListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_someone_who_may_not_see_every_matter_sees_only_their_own(): void
    {
        $user = User::factory()->create();
        $mine = $this->seat(Matter::factory()->create(), $user);
        Matter::factory()->create();

        $list = (new MatterList)->for($user, 'all', 'reference', null);

        $this->assertSame([$mine->id], $list['matters']->pluck('id')->all());
    }

    public function test_each_filter_lists_only_its_own_matters(): void
    {
        $user = $this->seeingEverything();
        $mine = $this->seat(Matter::factory()->create(), $user);
        $unassigned = Matter::factory()->create();
        $closed = Matter::factory()->closed()->create();

        $ids = fn (string $filter) => (new MatterList)->for($user, $filter, 'reference', null)['matters']->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$mine->id, $unassigned->id], $ids('all'));
        $this->assertSame([$mine->id], $ids('mine'));
        $this->assertSame([$unassigned->id], $ids('unassigned'));
        $this->assertSame([$closed->id], $ids('closed'));
    }

    public function test_it_narrows_to_one_matter_type(): void
    {
        $type = MatterType::factory()->create();
        $ofType = Matter::factory()->for($type)->create();
        Matter::factory()->create();

        $list = (new MatterList)->for($this->seeingEverything(), 'all', 'reference', $type->id);

        $this->assertSame([$ofType->id], $list['matters']->pluck('id')->all());
    }

    public function test_by_next_date_matters_with_no_pending_entry_come_last(): void
    {
        $none = Matter::factory()->create();
        $later = Matter::factory()->create();
        $sooner = Matter::factory()->create();
        DiaryEntry::factory()->for($later)->dueIn(5)->create();
        DiaryEntry::factory()->for($sooner)->dueIn(1)->create();

        $list = (new MatterList)->for($this->seeingEverything(), 'all', 'next_date', null);

        $this->assertSame([$sooner->id, $later->id, $none->id], $list['matters']->pluck('id')->all());
    }

    public function test_it_orders_by_reference_and_by_date_opened(): void
    {
        $older = Matter::factory()->create(['reference' => 'CIV/2/2026', 'instructed_at' => now()->subMonth()]);
        $newer = Matter::factory()->create(['reference' => 'CIV/1/2026', 'instructed_at' => now()]);
        $user = $this->seeingEverything();

        $this->assertSame([$newer->id, $older->id], (new MatterList)->for($user, 'all', 'reference', null)['matters']->pluck('id')->all());
        $this->assertSame([$newer->id, $older->id], (new MatterList)->for($user, 'all', 'opened', null)['matters']->pluck('id')->all());
    }

    public function test_it_counts_the_matters_under_each_filter(): void
    {
        $user = $this->seeingEverything();
        $this->seat(Matter::factory()->create(), $user);
        Matter::factory()->create();
        Matter::factory()->closed()->create();

        $list = (new MatterList)->for($user, 'all', 'reference', null);

        $this->assertSame(['open' => 2, 'mine' => 1, 'unassigned' => 1, 'closed' => 1, 'closed_this_year' => 1], $list['counts']);
    }

    protected function seeingEverything(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::ViewAllMatters->value);

        return $user;
    }

    protected function seat(Matter $matter, User $user): Matter
    {
        MatterAssignment::factory()->for($matter)->for($user)->capacity(Assignment::Responsible)->create();

        return $matter;
    }
}
