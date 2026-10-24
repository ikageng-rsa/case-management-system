<?php

declare(strict_types=1);

namespace Tests\Feature\Matter;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Document\DocumentKind;
use App\Enums\Matter\Assignment;
use App\Models\Diary\DiaryEntry;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\Reference\ActivityType;
use App\Models\Reference\Court;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MatterWorkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
        Storage::fake(config('media-library.disk_name'));
    }

    /** An attorney carrying the matter, without the view-all permission. */
    protected function assignedAttorney(Matter $matter): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleEnum::Attorney->value);

        MatterAssignment::factory()->for($matter)->for($user)->capacity(Assignment::Responsible)->create();

        return $user;
    }

    public function test_an_assigned_user_can_add_a_narration(): void
    {
        $matter = Matter::factory()->create();
        $user = $this->assignedAttorney($matter);
        $type = ActivityType::factory()->create();

        $this->actingAs($user)
            ->post(route('matter.narrations.store', $matter), [
                'activity_type_id' => $type->getKey(),
                'quantity' => 30,
                'body' => 'Telephone consultation with client.',
            ])
            ->assertRedirect(route('matter.show', [$matter, 'tab' => 'ledger']));

        $this->assertDatabaseHas('narrations', [
            'matter_id' => $matter->getKey(),
            'author_id' => $user->getKey(),
            'activity_type_id' => $type->getKey(),
        ]);
    }

    public function test_an_appearance_needs_the_matter_to_have_a_court(): void
    {
        $matter = Matter::factory()->create(['court_id' => null]);
        $user = $this->assignedAttorney($matter);
        $appearance = ActivityType::factory()->appearance()->create();

        $this->actingAs($user)
            ->post(route('matter.narrations.store', $matter), [
                'activity_type_id' => $appearance->getKey(),
                'quantity' => 60,
                'body' => 'Appeared in motion court.',
            ])
            ->assertSessionHasErrorsIn('narration', 'activity_type_id');

        $matter->update(['court_id' => null]);
        $matter->court()->associate(Court::factory()->create())->save();

        $this->actingAs($user)
            ->post(route('matter.narrations.store', $matter), [
                'activity_type_id' => $appearance->getKey(),
                'quantity' => 60,
                'body' => 'Appeared in motion court.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('narrations', 1);
    }

    public function test_an_unassigned_user_or_a_closed_matter_cannot_take_new_work(): void
    {
        $type = ActivityType::factory()->create();
        $payload = ['activity_type_id' => $type->getKey(), 'quantity' => 6, 'body' => 'Work.'];

        $open = Matter::factory()->create();
        $stranger = User::factory()->create();
        $stranger->assignRole(RoleEnum::Attorney->value);

        $this->actingAs($stranger)->post(route('matter.narrations.store', $open), $payload)->assertForbidden();

        $closed = Matter::factory()->closed()->create();
        $assigned = $this->assignedAttorney($closed);

        $this->actingAs($assigned)->post(route('matter.narrations.store', $closed), $payload)->assertForbidden();
        $this->assertDatabaseCount('narrations', 0);
    }

    public function test_a_diary_entry_can_be_added_and_completed(): void
    {
        $matter = Matter::factory()->create();
        $user = $this->assignedAttorney($matter);

        $this->actingAs($user)
            ->post(route('matter.diary.store', $matter), [
                'body' => 'File heads of argument.',
                'due_at' => now()->addWeek()->toDateTimeString(),
            ])
            ->assertRedirect(route('matter.show', [$matter, 'tab' => 'diary']));

        $entry = DiaryEntry::firstOrFail();
        $this->assertSame($user->getKey(), $entry->assigned_to);
        $this->assertTrue($entry->isPending());

        // Not due for a week yet, so it is not the person's to tick off.
        $this->actingAs($user)
            ->post(route('matter.diary.complete', [$matter, $entry]))
            ->assertSessionHasErrorsIn('diary_entries', 'diary');
        $this->assertTrue($entry->fresh()->isPending());

        $entry->forceFill(['due_at' => now()->subHour()])->save();

        $this->actingAs($user)
            ->post(route('matter.diary.complete', [$matter, $entry]))
            ->assertRedirect();

        $this->assertTrue($entry->fresh()->isComplete());
        $this->assertSame($user->getKey(), $entry->fresh()->completed_by);
    }

    public function test_a_diary_entry_from_another_matter_cannot_be_completed_through_this_one(): void
    {
        $matter = Matter::factory()->create();
        $user = $this->assignedAttorney($matter);
        $foreign = DiaryEntry::factory()->create();

        $this->actingAs($user)
            ->post(route('matter.diary.complete', [$matter, $foreign]))
            ->assertNotFound();

        $this->assertTrue($foreign->fresh()->isPending());
    }

    public function test_a_document_can_be_uploaded_downloaded_and_audited(): void
    {
        $matter = Matter::factory()->create();
        $user = $this->assignedAttorney($matter);

        $this->actingAs($user)
            ->post(route('matter.documents.store', $matter), [
                'file' => UploadedFile::fake()->create('bundle.pdf', 20, 'application/pdf'),
                'kind' => 'bundle',
            ])
            ->assertRedirect(route('matter.show', [$matter, 'tab' => 'documents']));

        $document = $matter->fresh()->getFirstMedia(Matter::DOCUMENTS);
        $this->assertSame('bundle', $document->getCustomProperty('kind'));

        $this->actingAs($user)
            ->get(route('matter.documents.download', [$matter, $document->getKey()]))
            ->assertOk()
            ->assertDownload('bundle.pdf');

        $this->assertDatabaseHas('activity_log', ['event' => 'downloaded', 'subject_id' => (string) $document->getKey()]);
    }

    public function test_only_users_with_the_permission_can_delete_a_document(): void
    {
        $matter = Matter::factory()->create();
        $attorney = $this->assignedAttorney($matter);
        $media = $matter->addDocument(UploadedFile::fake()->create('a.pdf', 5, 'application/pdf'), DocumentKind::Evidence);

        $this->actingAs($attorney)
            ->delete(route('matter.documents.destroy', [$matter, $media->getKey()]))
            ->assertForbidden();

        $director = User::factory()->create();
        $director->assignRole(RoleEnum::Director->value);

        $this->actingAs($director)
            ->delete(route('matter.documents.destroy', [$matter, $media->getKey()]))
            ->assertRedirect();

        $this->assertCount(0, $matter->fresh()->documents());
    }

    public function test_a_document_belonging_to_another_matter_is_not_reachable(): void
    {
        $matter = Matter::factory()->create();
        $user = $this->assignedAttorney($matter);
        $other = Matter::factory()->create();
        $media = $other->addDocument(UploadedFile::fake()->create('secret.pdf', 5, 'application/pdf'), DocumentKind::Evidence);

        $this->actingAs($user)
            ->get(route('matter.documents.download', [$matter, $media->getKey()]))
            ->assertNotFound();
    }

    public function test_the_outcome_of_an_action_reaches_the_toasts(): void
    {
        $matter = Matter::factory()->create();
        $user = $this->assignedAttorney($matter);
        $type = ActivityType::factory()->create();

        $this->actingAs($user)
            ->followingRedirects()
            ->post(route('matter.narrations.store', $matter), [
                'activity_type_id' => $type->getKey(),
                'quantity' => 6,
                'body' => 'Work.',
            ])
            ->assertSee('Narration added.');

        $this->actingAs($user)
            ->from(route('matter.show', $matter))
            ->followingRedirects()
            ->post(route('matter.narrations.store', $matter), ['quantity' => 6])
            ->assertSee('The body field is required.');
    }
}
