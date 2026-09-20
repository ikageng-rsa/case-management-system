<?php

namespace Tests\Unit\Models;

use App\Enums\Narration\ActivityMeasure;
use App\Models\ActivityType;
use App\Models\Matter;
use App\Models\Narration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NarrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stamps_when_the_work_happened(): void
    {
        $narration = Narration::factory()->create(['occurred_at' => null]);

        $this->assertNotNull($narration->occurred_at);
    }

    public function test_it_keeps_an_explicit_occurrence_date(): void
    {
        $narration = Narration::factory()->occurredAt(now()->subWeek())->create();

        $this->assertTrue($narration->occurred_at->isBefore(now()->subDays(6)));
    }

    public function test_it_belongs_to_a_matter_an_activity_type_and_an_author(): void
    {
        $narration = Narration::factory()->create();

        $this->assertInstanceOf(Matter::class, $narration->matter);
        $this->assertInstanceOf(ActivityType::class, $narration->activityType);
        $this->assertInstanceOf(User::class, $narration->author);
    }

    public function test_most_narrations_have_no_court(): void
    {
        $this->assertNull(Narration::factory()->create()->court);
    }

    public function test_an_appearance_records_the_court_attended(): void
    {
        $narration = Narration::factory()->appearance()->create();

        $this->assertNotNull($narration->court);
        $this->assertTrue($narration->activityType->requires_court);
    }

    public function test_it_bills_time_in_whole_increments(): void
    {
        $type = ActivityType::factory()->measuredIn(ActivityMeasure::Minutes, 6)->create();

        $narration = Narration::factory()->for($type, 'activityType')->create(['quantity' => 30]);

        $this->assertSame(5, $narration->billableUnits());
    }

    public function test_it_rounds_a_part_increment_up(): void
    {
        $type = ActivityType::factory()->measuredIn(ActivityMeasure::Minutes, 6)->create();

        $narration = Narration::factory()->for($type, 'activityType')->create(['quantity' => 4]);

        $this->assertSame(1, $narration->billableUnits());
    }

    public function test_it_bills_nothing_for_no_quantity(): void
    {
        $type = ActivityType::factory()->measuredIn(ActivityMeasure::Item)->create();

        $narration = Narration::factory()->for($type, 'activityType')->create(['quantity' => 0]);

        $this->assertSame(0, $narration->billableUnits());
    }

    public function test_it_scopes_narrations_by_matter_and_author(): void
    {
        $matter = Matter::factory()->create();
        $author = User::factory()->create();

        Narration::factory()->for($matter)->create(['author_id' => $author->id]);
        Narration::factory()->create();

        $this->assertCount(1, Narration::forMatter($matter)->get());
        $this->assertCount(1, Narration::by($author)->get());
        $this->assertCount(1, $author->narrations);
        $this->assertCount(1, $matter->narrations);
    }

    public function test_it_scopes_narrations_to_a_billing_period(): void
    {
        Narration::factory()->occurredAt(now()->subDays(3))->create();
        Narration::factory()->occurredAt(now()->subMonths(3))->create();

        $found = Narration::occurredBetween(now()->subWeek(), now())->get();

        $this->assertCount(1, $found);
    }

    public function test_it_is_deleted_with_its_matter(): void
    {
        $matter = Matter::factory()->create();
        Narration::factory()->for($matter)->create();

        $matter->forceDelete();

        $this->assertSame(0, Narration::query()->count());
    }
}
