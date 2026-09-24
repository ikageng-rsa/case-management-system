<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\Narration\ActivityMeasure;
use App\Models\ActivityType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_names_a_time_unit_by_its_increment(): void
    {
        $type = ActivityType::factory()->measuredIn(ActivityMeasure::Minutes, 6)->create();

        $this->assertSame('6 minutes', $type->name);
    }

    public function test_it_names_a_single_minute_in_the_singular(): void
    {
        $type = ActivityType::factory()->measuredIn(ActivityMeasure::Minutes, 1)->create();

        $this->assertSame('per minute', $type->name);
    }

    public function test_it_names_a_single_non_time_unit_as_per_measure(): void
    {
        $page = ActivityType::factory()->measuredIn(ActivityMeasure::Page)->create();
        $item = ActivityType::factory()->measuredIn(ActivityMeasure::Item)->create();
        $kilometre = ActivityType::factory()->measuredIn(ActivityMeasure::Kilometre)->create();

        $this->assertSame('per page', $page->name);
        $this->assertSame('per item', $item->name);
        $this->assertSame('per kilometre', $kilometre->name);
    }

    public function test_it_pluralises_a_non_time_unit_above_one(): void
    {
        $pages = ActivityType::factory()->measuredIn(ActivityMeasure::Page, 3)->create();
        $items = ActivityType::factory()->measuredIn(ActivityMeasure::Item, 2)->create();
        $kilometres = ActivityType::factory()->measuredIn(ActivityMeasure::Kilometre, 10)->create();

        $this->assertSame('3 pages', $pages->name);
        $this->assertSame('2 items', $items->name);
        $this->assertSame('10 kilometres', $kilometres->name);
    }

    public function test_it_serialises_the_name_alongside_the_stored_columns(): void
    {
        $type = ActivityType::factory()->measuredIn(ActivityMeasure::Page, 3)->create();

        $this->assertSame('3 pages', $type->toArray()['name']);
    }

    public function test_it_bills_in_whole_increments(): void
    {
        $type = ActivityType::factory()->measuredIn(ActivityMeasure::Minutes, 6)->create();

        $this->assertSame(5, $type->billableUnits(30));
        $this->assertSame(1, $type->billableUnits(4));
        $this->assertSame(0, $type->billableUnits(0));
    }

    public function test_it_scopes_billable_and_court_activities(): void
    {
        ActivityType::factory()->create();
        ActivityType::factory()->nonBillable()->create();
        ActivityType::factory()->appearance()->create();

        $this->assertCount(2, ActivityType::billable()->get());
        $this->assertCount(1, ActivityType::requiringCourt()->get());
    }
}
