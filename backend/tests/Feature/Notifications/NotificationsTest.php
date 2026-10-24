<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Matter\Assignment;
use App\Enums\Notification\NotificationType;
use App\Models\Client\Client;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
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

    // ---- seeing them: the list that drops down from the bell ----

    protected function addNotification(User $user, array $data, mixed $readAt = null): string
    {
        $id = (string) Str::uuid();

        $user->notifications()->create([
            'id' => $id, 'type' => MatterAssigned::class, 'data' => $data, 'read_at' => $readAt,
        ]);

        return $id;
    }

    public function test_what_was_sent_to_the_system_is_in_the_list_with_a_link(): void
    {
        $matter = Matter::factory()->create(['reference' => 'IOT/CIV/105/2026']);
        $person = User::factory()->create();
        $actor = $this->director();

        $this->actingAs($actor);
        app(AssignUserToMatter::class)->assign($matter, $person, Assignment::Responsible);

        $this->actingAs($person)
            ->getJson(route('notifications'))
            ->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('items.0.message', $actor->name.' put you on matter IOT/CIV/105/2026 as responsible.')
            ->assertJsonPath('items.0.url', route('matter.show', $matter))
            ->assertJsonPath('items.0.read', false);
    }

    public function test_the_list_is_the_newest_ten(): void
    {
        $person = User::factory()->create();

        foreach (range(1, 12) as $n) {
            $this->addNotification($person, ['message' => "Number {$n}", 'url' => null]);
            $this->travel(1)->minutes();
        }

        $items = $this->actingAs($person)->getJson(route('notifications'))->json('items');

        $this->assertCount(10, $items);
        $this->assertSame('Number 12', $items[0]['message']);
        $this->assertSame('Number 3', $items[9]['message']);
    }

    public function test_the_bell_in_the_header_knows_the_unread_count_and_fetches_the_list_when_opened(): void
    {
        $person = User::factory()->create();
        $this->addNotification($person, ['message' => 'One', 'url' => null]);
        $this->addNotification($person, ['message' => 'Two', 'url' => null], now());

        $this->actingAs($person)
            ->get(route('dashboard'))
            ->assertSee('notificationMenu(', false)
            ->assertSee('Mark all as read')
            ->assertDontSee('Two');

        $this->actingAs($person)->getJson(route('notifications'))->assertJsonPath('unread', 1);
    }

    public function test_marking_one_read_says_where_it_points_and_how_many_are_left(): void
    {
        $matter = Matter::factory()->create();
        $person = User::factory()->create();
        $id = $this->addNotification($person, ['message' => 'You were put on it.', 'url' => route('matter.show', $matter)]);
        $this->addNotification($person, ['message' => 'Another.', 'url' => null]);

        $this->actingAs($person)
            ->postJson(route('notifications.read', $id))
            ->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('item.read', true)
            ->assertJsonPath('item.url', route('matter.show', $matter));

        $this->assertNotNull($person->notifications()->find($id)->read_at);
    }

    public function test_one_with_nowhere_to_go_has_no_link(): void
    {
        $person = User::factory()->create();
        $this->addNotification($person, ['message' => 'Taken off.', 'url' => null]);

        $this->actingAs($person)->getJson(route('notifications'))->assertJsonPath('items.0.url', null);
    }

    public function test_a_link_to_another_site_is_never_offered(): void
    {
        $person = User::factory()->create();
        $this->addNotification($person, ['message' => 'x', 'url' => 'https://evil.example/phish']);

        $this->actingAs($person)->getJson(route('notifications'))->assertJsonPath('items.0.url', null);
    }

    public function test_nobody_can_open_or_see_another_persons_notifications(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $id = $this->addNotification($owner, ['message' => 'Private to the owner.', 'url' => null]);

        $this->actingAs($other)->postJson(route('notifications.read', $id))->assertNotFound();
        $this->actingAs($other)->getJson(route('notifications'))->assertJsonPath('items', [])->assertJsonPath('unread', 0);

        $this->assertNull($owner->notifications()->find($id)->read_at);
    }

    public function test_mark_all_as_read_touches_only_the_persons_own(): void
    {
        $person = User::factory()->create();
        $other = User::factory()->create();

        foreach (['One', 'Two'] as $text) {
            $this->addNotification($person, ['message' => $text, 'url' => null]);
        }

        $this->addNotification($other, ['message' => 'Not yours', 'url' => null]);

        $this->actingAs($person)->postJson(route('notifications.read-all'))->assertOk()->assertJsonPath('unread', 0);

        $this->assertSame(0, $person->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_the_notifications_address_serves_the_list_as_data_not_a_page(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('notifications'));

        $response->assertOk();
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
        $this->assertStringNotContainsString('<html', $response->getContent());
    }

    public function test_the_list_is_for_signed_in_users(): void
    {
        $this->getJson(route('notifications'))->assertUnauthorized();
        $this->postJson(route('notifications.read-all'))->assertUnauthorized();
    }

    // ---- the New matter button in the header ----

    public function test_the_header_offers_new_matter_to_those_who_may_open_one(): void
    {
        $secretary = User::factory()->create();
        $secretary->assignRole(RoleEnum::Secretary->value);
        $manager = User::factory()->create();
        $manager->assignRole(RoleEnum::Manager->value);

        $this->actingAs($secretary)->get(route('dashboard'))
            ->assertSee('href="'.route('matters', ['new' => 1]).'"', false)
            ->assertSee('New matter');

        $this->actingAs($manager)->get(route('dashboard'))
            ->assertDontSee('New matter');
    }

    public function test_on_the_matters_page_the_button_opens_the_form_where_you_are(): void
    {
        $secretary = User::factory()->create();
        $secretary->assignRole(RoleEnum::Secretary->value);

        $this->actingAs($secretary)->get(route('matters'))
            ->assertDontSee('href="'.route('matters', ['new' => 1]).'"', false)
            ->assertSee("sheet-open', 'new-matter'", false);
    }

    public function test_arriving_from_the_button_opens_the_form(): void
    {
        $secretary = User::factory()->create();
        $secretary->assignRole(RoleEnum::Secretary->value);

        $this->actingAs($secretary)->get(route('matters', ['new' => 1]))
            ->assertOk()
            ->assertSee("\$dispatch('sheet-open', 'new-matter');", false);

        $this->actingAs($secretary)->get(route('matters'))
            ->assertDontSee("searchParams.delete('new')", false);
    }

    public function test_the_form_clears_the_new_parameter_from_the_address_once_it_has_opened(): void
    {
        $secretary = User::factory()->create();
        $secretary->assignRole(RoleEnum::Secretary->value);

        $this->actingAs($secretary)->get(route('matters', ['new' => 1]))
            ->assertSee("searchParams.delete('new')", false)
            ->assertSee('history.replaceState', false);
    }

    public function test_paging_does_not_carry_the_new_parameter_along(): void
    {
        $secretary = User::factory()->create();
        $secretary->assignRole(RoleEnum::Secretary->value);
        Matter::factory()->count(20)->create();

        $html = $this->actingAs($secretary)->get(route('matters', ['new' => 1, 'filter' => 'all']))->getContent();

        $this->assertStringContainsString('page=2', $html);
        $this->assertStringNotContainsString('new=1&amp;page=2', $html);
        $this->assertStringNotContainsString('page=2&amp;new=1', $html);
    }
}
