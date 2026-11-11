<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Diary;

use App\Models\Matter\Matter;
use App\Models\User\User;
use App\Notifications\DiaryEntryAssigned;
use App\Services\Diary\DiariseNextAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DiariseNextActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_diarises_an_action_against_a_matter_and_assignee(): void
    {
        $matter = Matter::factory()->create();
        $assignee = User::factory()->create();

        $entry = app(DiariseNextAction::class)->diarise($matter, $assignee, 'Serve the court order.', now()->addWeek());

        $this->assertTrue($entry->exists);
        $this->assertTrue($entry->matter->is($matter));
        $this->assertTrue($entry->assignee->is($assignee));
        $this->assertTrue($entry->isPending());
    }

    public function test_a_future_entry_is_not_overdue(): void
    {
        $entry = app(DiariseNextAction::class)->diarise(
            Matter::factory()->create(),
            User::factory()->create(),
            'Follow up with client.',
            now()->addDays(3),
        );

        $this->assertFalse($entry->isOverdue());
    }

    public function test_it_keeps_the_reminder_lead_it_is_given(): void
    {
        $entry = app(DiariseNextAction::class)->diarise(
            Matter::factory()->create(),
            User::factory()->create(),
            'File the plea.',
            now()->addDays(3),
            remindBeforeMinutes: 60,
        );

        $this->assertSame(60, $entry->fresh()->remind_before_minutes);
    }

    public function test_the_assignee_is_told_only_when_someone_else_diarised_it(): void
    {
        Notification::fake();
        $assignee = User::factory()->create();
        $colleague = User::factory()->create();

        app(DiariseNextAction::class)->diarise(Matter::factory()->create(), $assignee, 'Call the sheriff.', now()->addDay(), diarisedBy: $assignee);
        Notification::assertNothingSent();

        app(DiariseNextAction::class)->diarise(Matter::factory()->create(), $assignee, 'Call the sheriff.', now()->addDay(), diarisedBy: $colleague);
        Notification::assertSentTo($assignee, DiaryEntryAssigned::class);
    }
}
