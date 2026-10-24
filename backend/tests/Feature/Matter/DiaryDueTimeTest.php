<?php

declare(strict_types=1);

namespace Tests\Feature\Matter;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Matter\Assignment;
use App\Models\DiaryEntry;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiaryDueTimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    /** @return array{0: User, 1: Matter} */
    protected function carrier(): array
    {
        $matter = Matter::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(RoleEnum::Attorney->value);
        MatterAssignment::factory()->for($matter)->for($user)->capacity(Assignment::Responsible)->create();

        return [$user, $matter];
    }

    public function test_an_entry_that_is_not_yet_due_cannot_be_marked_done(): void
    {
        [$user, $matter] = $this->carrier();
        $entry = DiaryEntry::factory()->for($matter)->dueIn(3)->create();

        $this->actingAs($user)
            ->post(route('matter.diary.complete', [$matter, $entry]))
            ->assertSessionHasErrorsIn('diary_entries', 'diary');

        $this->assertTrue($entry->fresh()->isPending());
    }

    public function test_the_refusal_names_when_it_falls_due(): void
    {
        [$user, $matter] = $this->carrier();
        $entry = DiaryEntry::factory()->for($matter)->create(['due_at' => now()->addDays(2)->setTime(9, 30)]);

        $errors = $this->actingAs($user)
            ->post(route('matter.diary.complete', [$matter, $entry]))
            ->assertSessionHasErrorsIn('diary_entries', 'diary')
            ->getSession()->get('errors')->getBag('diary_entries')->first('diary');

        $this->assertStringContainsString($entry->due_at->format('d M Y H:i'), $errors);
    }

    public function test_an_entry_that_has_fallen_due_can_be_marked_done(): void
    {
        [$user, $matter] = $this->carrier();
        $entry = DiaryEntry::factory()->for($matter)->overdue()->create();

        $this->actingAs($user)
            ->post(route('matter.diary.complete', [$matter, $entry]))
            ->assertSessionHasNoErrors();

        $this->assertTrue($entry->fresh()->isComplete());
        $this->assertSame($user->getKey(), $entry->fresh()->completed_by);
    }

    public function test_the_moment_it_falls_due_it_can_be_done(): void
    {
        [$user, $matter] = $this->carrier();
        $entry = DiaryEntry::factory()->for($matter)->create(['due_at' => now()->addMinutes(10)]);

        $this->actingAs($user)->post(route('matter.diary.complete', [$matter, $entry]))->assertSessionHasErrorsIn('diary_entries', 'diary');

        $this->travel(11)->minutes();

        $this->actingAs($user)->post(route('matter.diary.complete', [$matter, $entry]))->assertSessionHasNoErrors();
        $this->assertTrue($entry->fresh()->isComplete());
    }

    public function test_the_page_offers_the_button_only_once_the_entry_is_due(): void
    {
        [$user, $matter] = $this->carrier();
        DiaryEntry::factory()->for($matter)->dueIn(3)->create(['body' => 'Not yet']);
        DiaryEntry::factory()->for($matter)->overdue()->create(['body' => 'Past due']);

        $html = $this->actingAs($user)->get(route('matter.show', [$matter, 'tab' => 'diary']))->assertOk()->getContent();

        // One live control, for the entry that is due; the other is shown but cannot be used.
        $this->assertSame(1, substr_count($html, 'data-confirm-title="Complete diary entry"'));
        $this->assertStringContainsString('Not due until', $html);
        $this->assertMatchesRegularExpression('/<button[^>]*disabled[^>]*>\s*Mark done/s', $html);
    }

    public function test_an_entry_from_another_matter_still_cannot_be_reached(): void
    {
        [$user, $matter] = $this->carrier();
        $foreign = DiaryEntry::factory()->overdue()->create();

        $this->actingAs($user)
            ->post(route('matter.diary.complete', [$matter, $foreign]))
            ->assertNotFound();
    }
}
