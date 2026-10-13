<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Matter\Assignment;
use App\Enums\Notification\NotificationType;
use App\Models\Client;
use App\Models\Matter;
use App\Models\MatterAssignment;
use App\Models\MatterType;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\DiaryEntryAssigned;
use App\Notifications\MatterAssigned;
use App\Notifications\MatterUnassigned;
use App\Notifications\PersonalNotification;
use App\Services\Matters\AssignUserToMatter;
use App\Services\Notifications\NotifyUser;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    protected function director(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleEnum::Director->value);

        return $user;
    }

    // ---- when it is sent ----

    public function test_someone_put_on_a_matter_is_told_by_the_system_and_by_email(): void
    {
        Notification::fake();
        $matter = Matter::factory()->create();
        $person = User::factory()->create();

        $this->actingAs($this->director())
            ->post(route('matter.assign', $matter), ['user_id' => $person->getKey(), 'capacity' => 'assisting'])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($person, MatterAssigned::class, function ($notification, $channels) use ($matter) {
            return $channels === ['database', 'mail']
                && $notification->matter->is($matter)
                && $notification->capacity === Assignment::Assisting;
        });
    }

    public function test_it_goes_only_where_the_person_chose(): void
    {
        Notification::fake();
        $matter = Matter::factory()->create();
        $person = User::factory()->create();
        NotificationPreference::factory()->for($person)->create(['type' => 'matter_assigned', 'database' => true, 'mail' => false]);

        $this->actingAs($this->director())
            ->post(route('matter.assign', $matter), ['user_id' => $person->getKey(), 'capacity' => 'assisting']);

        Notification::assertSentTo($person, MatterAssigned::class, fn ($n, $channels) => $channels === ['database']);
    }

    public function test_a_person_who_wants_nothing_is_sent_nothing_anywhere(): void
    {
        $matter = Matter::factory()->create();
        $person = User::factory()->create();
        NotificationPreference::factory()->for($person)->create(['type' => 'matter_assigned', 'database' => false, 'mail' => false]);

        $this->actingAs($this->director())
            ->post(route('matter.assign', $matter), ['user_id' => $person->getKey(), 'capacity' => 'assisting']);

        $this->assertSame(0, $person->notifications()->count());
        $this->assertTrue($matter->fresh()->load('assignments')->isAssignedTo($person), 'the assignment itself still happens');
    }

    public function test_putting_yourself_on_a_matter_does_not_notify_you(): void
    {
        Notification::fake();
        $matter = Matter::factory()->create();
        $director = $this->director();

        $this->actingAs($director)
            ->post(route('matter.assign', $matter), ['user_id' => $director->getKey(), 'capacity' => 'responsible']);

        Notification::assertNothingSent();
    }

    public function test_assigning_someone_who_already_holds_the_capacity_notifies_nobody(): void
    {
        $matter = Matter::factory()->create();
        $person = User::factory()->create();
        MatterAssignment::factory()->for($matter)->for($person)->capacity(Assignment::Assisting)->create();
        Notification::fake();

        $this->actingAs($this->director())
            ->post(route('matter.assign', $matter), ['user_id' => $person->getKey(), 'capacity' => 'assisting']);

        Notification::assertNothingSent();
    }

    public function test_taking_someone_off_a_matter_tells_them_in_the_system_only_by_default(): void
    {
        $matter = Matter::factory()->create();
        $person = User::factory()->create();
        MatterAssignment::factory()->for($matter)->for($person)->capacity(Assignment::Assisting)->create();
        Notification::fake();

        $this->actingAs($this->director())
            ->delete(route('matter.unassign', $matter), ['user_id' => $person->getKey(), 'capacity' => 'assisting']);

        Notification::assertSentTo($person, MatterUnassigned::class, fn ($n, $channels) => $channels === ['database']);
    }

    public function test_a_diary_entry_given_to_someone_else_tells_them(): void
    {
        Notification::fake();
        $matter = Matter::factory()->create();
        $author = User::factory()->create();
        $author->assignRole(RoleEnum::Attorney->value);
        MatterAssignment::factory()->for($matter)->for($author)->capacity(Assignment::Responsible)->create();
        $assignee = User::factory()->create();

        $this->actingAs($author)
            ->post(route('matter.diary.store', $matter), [
                'body' => 'File heads of argument.', 'due_at' => now()->addWeek()->toDateTimeString(), 'assigned_to' => $assignee->getKey(),
            ])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($assignee, DiaryEntryAssigned::class);
        Notification::assertNotSentTo($author, DiaryEntryAssigned::class);
    }

    public function test_a_diary_entry_you_give_yourself_does_not_tell_you(): void
    {
        Notification::fake();
        $matter = Matter::factory()->create();
        $author = User::factory()->create();
        $author->assignRole(RoleEnum::Attorney->value);
        MatterAssignment::factory()->for($matter)->for($author)->capacity(Assignment::Responsible)->create();

        $this->actingAs($author)
            ->post(route('matter.diary.store', $matter), ['body' => 'Mine.', 'due_at' => now()->addWeek()->toDateTimeString()]);

        Notification::assertNothingSent();
    }

    public function test_opening_a_matter_with_a_responsible_attorney_tells_them(): void
    {
        Notification::fake();
        $secretary = User::factory()->create();
        $secretary->assignRole(RoleEnum::Attorney->value);
        $lead = User::factory()->create();

        $this->actingAs($secretary)->post(route('matter.store'), [
            'client_id' => Client::factory()->create()->getKey(),
            'matter_type_id' => MatterType::factory()->nonLitigation()->create()->getKey(),
            'responsible_attorney_id' => $lead->getKey(),
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($lead, MatterAssigned::class);
    }

    public function test_a_notification_that_cannot_be_sent_never_undoes_what_caused_it(): void
    {
        Exceptions::fake();

        $broken = new class extends PersonalNotification
        {
            public function type(): NotificationType
            {
                return NotificationType::MatterAssigned;
            }

            public function via(object $notifiable): array
            {
                return ['mail'];
            }

            public function toMail(object $notifiable): MailMessage
            {
                throw new \RuntimeException('The mail server is down.');
            }
        };

        app(NotifyUser::class)->about(User::factory()->create(), $broken);

        Exceptions::assertReported(fn (\RuntimeException $e) => $e->getMessage() === 'The mail server is down.');
    }

    // ---- seeing them ----

    public function test_what_was_sent_to_the_system_appears_for_the_person_with_a_link(): void
    {
        $matter = Matter::factory()->create(['reference' => 'IOT/CIV/105/2026']);
        $person = User::factory()->create();
        $actor = $this->director();

        $this->actingAs($actor);
        app(AssignUserToMatter::class)->assign($matter, $person, Assignment::Responsible);

        $this->actingAs($person)
            ->get(route('notifications'))
            ->assertOk()
            ->assertSee($actor->name.' put you on matter IOT/CIV/105/2026 as responsible.');
    }

    public function test_the_bell_counts_what_is_unread(): void
    {
        $person = User::factory()->create();
        $person->notifications()->create(['id' => (string) Str::uuid(), 'type' => MatterAssigned::class, 'data' => ['message' => 'One', 'url' => null]]);
        $person->notifications()->create(['id' => (string) Str::uuid(), 'type' => MatterAssigned::class, 'data' => ['message' => 'Two', 'url' => null], 'read_at' => now()]);

        $this->actingAs($person)
            ->get(route('dashboard'))
            ->assertSee('Notifications, 1 unread');
    }

    public function test_opening_one_marks_it_read_and_goes_where_it_points(): void
    {
        $matter = Matter::factory()->create();
        $person = User::factory()->create();
        $notification = $person->notifications()->create([
            'id' => (string) Str::uuid(), 'type' => MatterAssigned::class,
            'data' => ['message' => 'You were put on it.', 'url' => route('matter.show', $matter)],
        ]);

        $this->actingAs($person)
            ->post(route('notifications.read', $notification->getKey()))
            ->assertRedirect(route('matter.show', $matter));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_one_with_nowhere_to_go_returns_to_the_list(): void
    {
        $person = User::factory()->create();
        $notification = $person->notifications()->create([
            'id' => (string) Str::uuid(), 'type' => MatterUnassigned::class, 'data' => ['message' => 'Taken off.', 'url' => null],
        ]);

        $this->actingAs($person)
            ->post(route('notifications.read', $notification->getKey()))
            ->assertRedirect(route('notifications'));
    }

    public function test_a_link_to_another_site_is_never_followed(): void
    {
        $person = User::factory()->create();
        $notification = $person->notifications()->create([
            'id' => (string) Str::uuid(), 'type' => MatterAssigned::class, 'data' => ['message' => 'x', 'url' => 'https://evil.example/phish'],
        ]);

        $this->actingAs($person)
            ->post(route('notifications.read', $notification->getKey()))
            ->assertRedirect(route('notifications'));
    }

    public function test_nobody_can_open_or_see_another_persons_notifications(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $notification = $owner->notifications()->create([
            'id' => (string) Str::uuid(), 'type' => MatterAssigned::class, 'data' => ['message' => 'Private to the owner.', 'url' => null],
        ]);

        $this->actingAs($other)->post(route('notifications.read', $notification->getKey()))->assertNotFound();
        $this->actingAs($other)->get(route('notifications'))->assertDontSee('Private to the owner.');

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_as_read_and_the_unread_filter(): void
    {
        $person = User::factory()->create();

        foreach (['One', 'Two'] as $text) {
            $person->notifications()->create(['id' => (string) Str::uuid(), 'type' => MatterAssigned::class, 'data' => ['message' => $text, 'url' => null]]);
        }

        $this->actingAs($person)->get(route('notifications', ['show' => 'unread']))->assertSee('One')->assertSee('Two');

        $this->actingAs($person)->post(route('notifications.read-all'))->assertRedirect(route('notifications'));

        $this->assertSame(0, $person->unreadNotifications()->count());
        $this->actingAs($person)->get(route('notifications', ['show' => 'unread']))->assertSee('Nothing unread.');
    }

    public function test_the_notification_pages_are_for_signed_in_users(): void
    {
        $this->get(route('notifications'))->assertRedirect();
        $this->post(route('notifications.read-all'))->assertRedirect();
    }
}
