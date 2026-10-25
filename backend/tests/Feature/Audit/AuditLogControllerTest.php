<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Enums\Auth\Role as RoleEnum;
use App\Models\Matter\Matter;
use App\Models\Narration\Narration;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditLogControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    protected function userWithRole(RoleEnum $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role->value);

        return $user;
    }

    protected function entry(string $event, ?User $by = null, array $properties = []): Activity
    {
        return Activity::query()->create([
            'log_name' => 'matter',
            'description' => "someone {$event} something",
            'event' => $event,
            'causer_type' => $by ? 'user' : null,
            'causer_id' => $by?->getKey(),
            'properties' => $properties,
        ]);
    }

    public function test_the_page_is_gated_behind_view_audit_log(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Secretary))->get(route('audit-log'))->assertForbidden();
        $this->actingAs($this->userWithRole(RoleEnum::Attorney))->get(route('audit-log'))->assertOk();
    }

    public function test_it_pages_eight_at_a_time_newest_first(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);
        Activity::query()->delete();

        foreach (range(1, 20) as $number) {
            $this->entry("event_{$number}", $director);
        }

        $this->actingAs($director)
            ->get(route('audit-log'))
            ->assertViewHas('activities', fn ($page) => $page->perPage() === 8
                && $page->count() === 8
                && $page->total() === 20
                && $page->first()->event === 'event_20'
                && $page->last()->event === 'event_13');

        $this->actingAs($director)
            ->get(route('audit-log', ['page' => 3]))
            ->assertViewHas('activities', fn ($page) => $page->count() === 4 && $page->last()->event === 'event_1');
    }

    public function test_it_filters_by_actor_and_by_action(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);
        $clerk = $this->userWithRole(RoleEnum::Secretary);
        Activity::query()->delete();

        $this->entry('read', $director);
        $this->entry('read', $clerk);
        $this->entry('created', $clerk);

        $this->actingAs($director)
            ->get(route('audit-log', ['actor' => $clerk->getKey()]))
            ->assertViewHas('activities', fn ($page) => $page->total() === 2);

        $this->actingAs($director)
            ->get(route('audit-log', ['action' => 'read']))
            ->assertViewHas('activities', fn ($page) => $page->total() === 2);

        $this->actingAs($director)
            ->get(route('audit-log', ['actor' => $clerk->getKey(), 'action' => 'created']))
            ->assertViewHas('activities', fn ($page) => $page->total() === 1);
    }

    public function test_the_filters_keep_their_place_across_pages(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);
        Activity::query()->delete();

        foreach (range(1, 10) as $ignored) {
            $this->entry('read', $director);
        }

        $this->actingAs($director)
            ->get(route('audit-log', ['action' => 'read']))
            ->assertSee('action=read&amp;page=2', false);
    }

    public function test_it_shows_the_matter_an_entry_belongs_to(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);
        $matter = Matter::factory()->create(['reference' => 'IOT/CIV/105/2026']);
        $narration = Narration::factory()->for($matter)->create();
        Activity::query()->delete();

        activity('narration')->performedOn($narration)->causedBy($director)->event('read')->log('read it');

        $this->actingAs($director)
            ->get(route('audit-log'))
            ->assertSee('IOT/CIV/105/2026')
            ->assertSee('narration on matter IOT/CIV/105/2026');
    }

    public function test_an_entry_whose_subject_no_longer_exists_still_reads(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);
        Activity::query()->delete();
        $entry = $this->entry('deleted', $director);
        $entry->update(['subject_type' => 'document', 'subject_id' => '41']);

        $this->actingAs($director)
            ->get(route('audit-log'))
            ->assertOk()
            ->assertSee('matter#41');
    }

    public function test_an_entry_with_no_actor_reads_as_a_dash(): void
    {
        Activity::query()->delete();
        $this->entry('created');

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('audit-log'))
            ->assertOk()
            ->assertViewHas('activities', fn ($page) => $page->first()->causer === null);
    }

    public function test_an_entry_by_someone_since_deactivated_still_names_them(): void
    {
        Activity::query()->delete();
        $former = $this->userWithRole(RoleEnum::Attorney, ['name' => 'Lerato Former']);
        $entry = $this->entry('updated', $former);
        $former->delete();

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('audit-log', ['action' => 'updated']))
            ->assertOk()
            ->assertViewHas('details', fn ($details) => $details[$entry->getKey()]['actor'] === 'Lerato Former');
    }

    public function test_the_export_follows_its_own_permission(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Attorney))->get(route('audit-log.export'))->assertForbidden();
        $this->actingAs($this->userWithRole(RoleEnum::Manager))->get(route('audit-log.export'))->assertOk();
    }

    public function test_the_export_is_a_csv_of_the_filtered_log_and_is_itself_recorded(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);
        Activity::query()->delete();
        $this->entry('read', $director, ['ip' => '102.132.45.0']);
        $this->entry('created', $director);

        $response = $this->actingAs($director)
            ->get(route('audit-log.export', ['action' => 'read']))
            ->assertOk()
            ->assertDownload();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('102.132.45.0', $csv);
        $this->assertStringNotContainsString('created', $csv);
        $this->assertSame(1, Activity::query()->where('event', 'exported')->where('log_name', 'audit_log')->count());
    }

    public function test_the_export_button_only_shows_to_users_who_may_export(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Attorney))
            ->get(route('audit-log'))
            ->assertDontSee('export csv');

        $this->actingAs($this->userWithRole(RoleEnum::Manager))
            ->get(route('audit-log'))
            ->assertSee('export csv');
    }

    public function test_each_entry_carries_its_full_detail_for_the_sheet(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);
        $matter = Matter::factory()->create(['reference' => 'IOT/CIV/105/2026', 'title' => 'Old title']);
        Activity::query()->delete();

        $this->actingAs($director);
        $matter->update(['title' => 'New title']);
        $matter->recordAccess('exported', ['ip' => '102.132.45.0', 'format' => 'csv']);

        $response = $this->get(route('audit-log'))->assertOk()->assertSee('audit-entry');

        $details = $response->viewData('details');
        $updated = $details->first(fn ($detail) => $detail['event'] === 'updated');
        $exported = $details->first(fn ($detail) => $detail['event'] === 'exported');

        $this->assertSame([['field' => 'title', 'old' => 'Old title', 'new' => 'New title']], $updated['changes']);
        $this->assertSame($director->name, $updated['actor']);
        $this->assertSame('IOT/CIV/105/2026', $updated['matter']);
        $this->assertSame(route('matter.show', $matter), $updated['matterUrl']);

        $this->assertSame('102.132.45.0', $exported['ip']);
        $this->assertSame([['key' => 'format', 'value' => 'csv']], $exported['properties']);
        $this->assertSame([], $exported['changes']);
    }

    public function test_the_detail_of_an_entry_whose_subject_is_gone_says_so(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);
        Activity::query()->delete();
        $this->entry('deleted', $director)->update(['subject_type' => 'document', 'subject_id' => '41']);

        $detail = $this->actingAs($director)->get(route('audit-log'))->viewData('details')->first();

        $this->assertTrue($detail['subjectDeleted']);
        $this->assertSame('matter#41', $detail['subject']);
    }

    public function test_the_field_an_entry_names_reads_as_words_in_its_detail(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);
        Activity::query()->delete();
        $this->entry('revealed', $director, ['field' => 'registration_number', 'ip' => '102.132.45.0']);

        $detail = $this->actingAs($director)->get(route('audit-log'))->viewData('details')->first();

        $this->assertSame([['key' => 'field', 'value' => 'registration number']], $detail['properties']);
        // What the log holds is unchanged; only its reading is.
        $this->assertSame('registration_number', Activity::query()->firstOrFail()->properties['field']);
    }

    public function test_the_detail_sheet_has_a_single_close_control_in_its_header(): void
    {
        $html = $this->actingAs($this->userWithRole(RoleEnum::Director))->get(route('audit-log'))->getContent();

        $sheet = substr($html, strpos($html, 'data-sheet="audit-entry"'));
        $header = substr($sheet, 0, strpos($sheet, 'offcanvas-body'));

        $this->assertSame(1, substr_count($header, 'btn-close'));
    }
}
