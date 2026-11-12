<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Account;

use App\Enums\Auth\Role;
use App\Enums\Matter\Assignment;
use App\Models\Diary\DiaryEntry;
use App\Models\Matter\Matter;
use App\Models\User\User;
use App\Notifications\DiaryEntryAssigned;
use App\Notifications\MatterAssigned;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\Feature\Api\ApiTestCase;

class NotificationTest extends ApiTestCase
{
    public function test_notifications_need_a_token(): void
    {
        $this->getJson('/api/v1/notifications')->assertJsonApiError(401);
        $this->postJson('/api/v1/notifications/read-all')->assertJsonApiError(401);
    }

    public function test_my_notifications_are_listed_newest_first_with_what_they_are_about(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = Matter::factory()->create();
        $entry = DiaryEntry::factory()->for($matter)->create();
        $this->travelTo(now()->subHour());
        $user->notifyNow(new MatterAssigned($matter, Assignment::Assisting, null), ['database']);
        $this->travelBack();
        $user->notifyNow(new DiaryEntryAssigned($entry, null), ['database']);
        User::factory()->create()->notifyNow(new MatterAssigned($matter, Assignment::Assisting, null), ['database']);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'notifications')
            ->assertJsonPath('data.0.attributes.kind', 'diary_assigned')
            ->assertJsonPath('data.0.attributes.matter_id', $matter->id)
            ->assertJsonPath('data.0.attributes.diary_entry_id', (string) $entry->id)
            ->assertJsonPath('data.0.attributes.read_at', null)
            ->assertJsonPath('data.1.attributes.kind', 'matter_assigned')
            ->assertJsonPath('data.1.attributes.matter_id', $matter->id)
            ->assertJsonPath('data.1.attributes.diary_entry_id', null)
            ->assertJsonPath('meta.unread', 2)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonMissingPath('data.0.attributes.url');
    }

    public function test_the_list_can_hold_only_what_is_unread(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $read = $this->notification($user, now());
        $unread = $this->notification($user);

        $this->getJson('/api/v1/notifications?unread=1')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unread->id)
            ->assertJsonPath('meta.unread', 1);

        $this->getJson('/api/v1/notifications')->assertJsonCount(2, 'data');
        $this->assertNotNull($read->fresh()->read_at);
    }

    public function test_one_is_marked_read(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $notification = $this->notification($user);

        $this->postJson("/api/v1/notifications/{$notification->id}/read")->assertNoContent();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_someone_elses_notification_is_not_found(): void
    {
        $this->signInAs(Role::CandidateAttorney);
        $theirs = $this->notification(User::factory()->create());

        $this->postJson("/api/v1/notifications/{$theirs->id}/read")->assertJsonApiError(404);
        $this->postJson('/api/v1/notifications/'.Str::uuid().'/read')->assertJsonApiError(404);

        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_all_of_mine_are_marked_read_and_no_one_elses(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $mine = [$this->notification($user), $this->notification($user)];
        $theirs = $this->notification(User::factory()->create());

        $this->postJson('/api/v1/notifications/read-all')->assertNoContent();

        foreach ($mine as $notification) {
            $this->assertNotNull($notification->fresh()->read_at);
        }

        $this->assertNull($theirs->fresh()->read_at);
        $this->getJson('/api/v1/notifications')->assertJsonPath('meta.unread', 0);
    }

    private function notification(User $user, mixed $readAt = null): DatabaseNotification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => MatterAssigned::class,
            'data' => ['type' => 'matter_assigned', 'message' => 'You were put on a matter.'],
            'read_at' => $readAt,
        ]);
    }
}
