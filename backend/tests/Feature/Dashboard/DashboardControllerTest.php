<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Matter\Assignment;
use App\Models\DiaryEntry;
use App\Models\Matter;
use App\Models\MatterAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    public function test_a_director_sees_every_open_matter(): void
    {
        $director = User::factory()->create();
        $director->assignRole(RoleEnum::Director->value);

        Matter::factory()->count(3)->create();
        Matter::factory()->closed()->create();

        $this->actingAs($director)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('openMattersCount', 3);
    }

    public function test_an_attorney_without_the_view_all_permission_only_sees_their_own_open_matters(): void
    {
        $attorney = User::factory()->create();
        $attorney->assignRole(RoleEnum::Attorney->value);

        $own = Matter::factory()->create();
        MatterAssignment::factory()->for($own)->for($attorney)->capacity(Assignment::Responsible)->create();

        Matter::factory()->create(); // assigned to someone else

        $this->actingAs($attorney)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('openMattersCount', 1);
    }

    public function test_awaiting_action_counts_are_scoped_to_the_signed_in_user_regardless_of_permission(): void
    {
        $director = User::factory()->create();
        $director->assignRole(RoleEnum::Director->value);

        DiaryEntry::factory()->overdue()->create(['assigned_to' => $director->id]);
        DiaryEntry::factory()->overdue()->create(); // someone else's

        $this->actingAs($director)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('overdueCount', 1);
    }

    public function test_a_matter_with_an_overdue_diary_entry_is_flagged_for_attention(): void
    {
        $director = User::factory()->create();
        $director->assignRole(RoleEnum::Director->value);

        $matter = Matter::factory()->create();
        DiaryEntry::factory()->for($matter)->overdue()->create();

        $this->actingAs($director)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('mattersNeedingAttention', function ($rows) use ($matter) {
                $row = $rows->first(fn (array $row) => $row['matter']->is($matter));

                return $row !== null && $row['label'] === 'Overdue action' && $row['tone'] === 'danger';
            });
    }

    public function test_a_matter_with_nothing_outstanding_is_on_track(): void
    {
        $director = User::factory()->create();
        $director->assignRole(RoleEnum::Director->value);

        $matter = Matter::factory()->create(['prescribes_at' => now()->addYear()]);

        $this->actingAs($director)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('mattersNeedingAttention', function ($rows) use ($matter) {
                $row = $rows->first(fn (array $row) => $row['matter']->is($matter));

                return $row !== null && $row['label'] === 'On track' && $row['tone'] === 'success';
            });
    }
}
