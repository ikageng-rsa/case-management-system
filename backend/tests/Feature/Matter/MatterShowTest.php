<?php

declare(strict_types=1);

namespace Tests\Feature\Matter;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Document\DocumentKind;
use App\Enums\Matter\Assignment;
use App\Models\DiaryEntry;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\Narration;
use App\Models\Reference\ActivityType;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MatterShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    protected function userWithRole(RoleEnum $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    public function test_the_url_uses_hyphens_in_place_of_the_slashes_in_a_reference(): void
    {
        $matter = Matter::factory()->create(['reference' => 'IOT/CIV/105/2026']);

        $this->assertSame('IOT-CIV-105-2026', $matter->getRouteKey());

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('matter.show', $matter))
            ->assertOk()
            ->assertSee('IOT/CIV/105/2026');
    }

    public function test_a_user_cannot_open_a_matter_they_are_not_assigned_to(): void
    {
        $matter = Matter::factory()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Attorney))
            ->get(route('matter.show', $matter))
            ->assertForbidden();
    }

    public function test_every_tab_renders(): void
    {
        $matter = Matter::factory()->create();

        $director = $this->userWithRole(RoleEnum::Director);

        foreach (['ledger', 'documents', 'parties', 'diary', 'audit'] as $tab) {
            $this->actingAs($director)
                ->get(route('matter.show', [$matter, 'tab' => $tab]))
                ->assertOk()
                ->assertViewHas('tab', $tab);
        }
    }

    public function test_the_audit_tab_is_hidden_from_users_without_the_permission(): void
    {
        $matter = Matter::factory()->create();
        $user = $this->userWithRole(RoleEnum::Secretary);

        $this->actingAs($user)
            ->get(route('matter.show', [$matter, 'tab' => 'audit']))
            ->assertOk()
            ->assertViewHas('tab', 'ledger')
            ->assertDontSee('Audit trail');
    }

    public function test_the_audit_tab_lists_what_happened_on_the_matter(): void
    {
        $matter = Matter::factory()->create();
        $director = $this->userWithRole(RoleEnum::Director);

        $this->actingAs($director)->get(route('matter.show', $matter));

        $this->actingAs($director)
            ->get(route('matter.show', [$matter, 'tab' => 'audit']))
            ->assertOk()
            ->assertSee('read matter '.$matter->reference);
    }

    public function test_the_ledger_can_be_filtered_by_activity_type_and_author(): void
    {
        $matter = Matter::factory()->create();
        $director = $this->userWithRole(RoleEnum::Director);

        $call = ActivityType::factory()->create(['code' => 'CALL']);
        $filing = ActivityType::factory()->create(['code' => 'FILE']);

        Narration::factory()->for($matter)->create(['activity_type_id' => $call->getKey(), 'author_id' => $director->getKey()]);
        Narration::factory()->for($matter)->count(2)->create(['activity_type_id' => $filing->getKey()]);

        $this->actingAs($director)
            ->get(route('matter.show', [$matter, 'filter' => 'FILE']))
            ->assertViewHas('narrations', fn ($narrations) => $narrations->count() === 2)
            ->assertViewHas('allCount', 3);

        $this->actingAs($director)
            ->get(route('matter.show', [$matter, 'filter' => 'mine']))
            ->assertViewHas('narrations', fn ($narrations) => $narrations->count() === 1);
    }

    public function test_the_ledger_exports_as_csv(): void
    {
        $matter = Matter::factory()->create();
        Narration::factory()->for($matter)->create(['body' => 'Called the client']);

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('matter.narrations.export', $matter))
            ->assertOk()
            ->assertDownload()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_the_ledger_export_carries_the_ledger_filter(): void
    {
        $matter = Matter::factory()->create();
        $director = $this->userWithRole(RoleEnum::Director);
        Narration::factory()->for($matter)->for($director, 'author')->create(['body' => 'Called the client']);
        Narration::factory()->for($matter)->create(['body' => 'Drafted the summons']);

        $csv = $this->actingAs($director)
            ->get(route('matter.narrations.export', [$matter, 'filter' => 'mine']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Called the client', $csv);
        $this->assertStringNotContainsString('Drafted the summons', $csv);
    }

    public function test_parties_shows_the_team_and_lets_an_authorised_user_assign_and_unassign(): void
    {
        $matter = Matter::factory()->create();
        $attorney = $this->userWithRole(RoleEnum::Attorney);
        $director = $this->userWithRole(RoleEnum::Director);

        $this->actingAs($director)
            ->post(route('matter.assign', $matter), ['user_id' => $attorney->getKey(), 'capacity' => 'responsible'])
            ->assertRedirect(route('matter.show', [$matter, 'tab' => 'parties']));

        $this->assertTrue($matter->fresh()->load('assignments')->isAssignedTo($attorney));

        $this->actingAs($director)
            ->delete(route('matter.unassign', $matter), ['user_id' => $attorney->getKey(), 'capacity' => 'responsible'])
            ->assertRedirect();

        $this->assertFalse($matter->fresh()->load('assignments')->isAssignedTo($attorney));
    }

    public function test_assigning_requires_the_permission(): void
    {
        $matter = Matter::factory()->create();
        $candidate = $this->userWithRole(RoleEnum::CandidateAttorney);
        MatterAssignment::factory()->for($matter)->for($candidate)->capacity(Assignment::Assisting)->create();

        $this->actingAs($candidate)
            ->post(route('matter.assign', $matter), ['user_id' => $candidate->getKey(), 'capacity' => 'responsible'])
            ->assertForbidden();
    }

    public function test_assigning_to_a_closed_matter_reports_the_reason(): void
    {
        $matter = Matter::factory()->closed()->create();
        $attorney = $this->userWithRole(RoleEnum::Attorney);

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->post(route('matter.assign', $matter), ['user_id' => $attorney->getKey(), 'capacity' => 'assisting'])
            ->assertSessionHasErrorsIn('assignment', 'matter');
    }

    public function test_the_assign_sheet_lists_people_only_for_users_who_may_assign(): void
    {
        $matter = Matter::factory()->create();
        $attorney = $this->userWithRole(RoleEnum::Attorney);
        MatterAssignment::factory()->for($matter)->for($attorney)->capacity(Assignment::Assisting)->create();

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('matter.show', $matter))
            ->assertViewHas('assignable', fn ($users) => $users->isNotEmpty());

        $this->actingAs($this->userWithRole(RoleEnum::CandidateAttorney)->fresh())
            ->get(route('matter.show', $matter))
            ->assertForbidden();
    }

    public function test_completing_a_diary_entry_asks_for_confirmation(): void
    {
        $matter = Matter::factory()->create();
        $attorney = $this->userWithRole(RoleEnum::Attorney);
        MatterAssignment::factory()->for($matter)->for($attorney)->capacity(Assignment::Responsible)->create();
        DiaryEntry::factory()->for($matter)->overdue()->create();

        $this->actingAs($attorney)
            ->get(route('matter.show', [$matter, 'tab' => 'diary']))
            ->assertSee('data-confirm-title="Complete diary entry"', false)
            ->assertSee('data-confirm-icon="circle-check"', false);
    }

    public function test_the_assign_form_knows_who_would_be_replaced_so_it_can_ask_first(): void
    {
        $matter = Matter::factory()->create();
        $holder = $this->userWithRole(RoleEnum::Attorney);
        MatterAssignment::factory()->for($matter)->for($holder)->capacity(Assignment::Responsible)->create();

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('matter.show', $matter))
            ->assertSee('x-bind:data-confirm', false)
            ->assertSee('data-confirm-icon="users"', false)
            // the holder of an exclusive capacity is handed to the form; a shared capacity is not
            ->assertSee($holder->getKey(), false)
            ->assertDontSee('\u0022assisting\u0022:{', false);
    }

    public function test_the_documents_filter_only_offers_kinds_that_have_documents(): void
    {
        Storage::fake(config('media-library.disk_name'));
        $matter = Matter::factory()->create();
        $matter->addDocument(UploadedFile::fake()->create('mandate.pdf', 5, 'application/pdf'), DocumentKind::Mandate);
        $matter->addDocument(UploadedFile::fake()->create('letter.pdf', 5, 'application/pdf'), DocumentKind::Correspondence);

        $html = $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('matter.show', [$matter, 'tab' => 'documents']))
            ->assertOk()
            ->assertSee('all 2')
            ->assertSee('mandate 1')
            ->assertSee('correspondence 1')
            ->assertDontSee('bundle 0')
            ->assertDontSee('court order 0')
            ->assertDontSee('report 0')
            ->getContent();

        $this->assertStringNotContainsString(' 0</a>', $html);
    }

    public function test_a_kind_being_looked_at_stays_even_when_it_is_empty(): void
    {
        $matter = Matter::factory()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('matter.show', [$matter, 'tab' => 'documents', 'kind' => 'bundle']))
            ->assertSee('bundle 0');
    }

    public function test_no_filter_row_is_shown_when_there_are_no_documents(): void
    {
        $matter = Matter::factory()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('matter.show', [$matter, 'tab' => 'documents']))
            ->assertOk()
            ->assertDontSee('rounded-pill p-1', false);
    }

    public function test_the_diary_only_offers_views_that_have_entries(): void
    {
        $matter = Matter::factory()->create();
        $director = $this->userWithRole(RoleEnum::Director);
        DiaryEntry::factory()->for($matter)->count(2)->create();
        DiaryEntry::factory()->for($matter)->overdue()->create();

        $this->actingAs($director)
            ->get(route('matter.show', [$matter, 'tab' => 'diary']))
            ->assertSee('pending 3')
            ->assertSee('overdue 1')
            ->assertDontSee('completed 0')
            ->assertDontSee('mine 0');
    }

    public function test_the_diary_shows_no_switcher_when_there_is_only_one_view(): void
    {
        $matter = Matter::factory()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('matter.show', [$matter, 'tab' => 'diary']))
            ->assertOk()
            ->assertDontSee('rounded-pill p-1', false)
            ->assertSee('No diary entries to show.');
    }

    public function test_the_ledger_hides_mine_when_it_is_empty_and_the_row_when_the_ledger_is(): void
    {
        $matter = Matter::factory()->create();
        $director = $this->userWithRole(RoleEnum::Director);

        $this->actingAs($director)
            ->get(route('matter.show', $matter))
            ->assertOk()
            ->assertDontSee('rounded-pill p-1', false);

        Narration::factory()->for($matter)->create();

        $this->actingAs($director)
            ->get(route('matter.show', $matter))
            ->assertSee('all 1')
            ->assertDontSee('mine 0');
    }

    public function test_the_ledger_offers_its_kinds_of_work_as_a_list_with_their_whole_names(): void
    {
        $matter = Matter::factory()->create();
        $call = ActivityType::factory()->create(['code' => 'CALL', 'name' => 'telephone attendance']);
        $admin = ActivityType::factory()->create(['code' => 'ADMIN', 'name' => 'file administration']);
        Narration::factory()->for($matter)->count(2)->create(['activity_type_id' => $call->getKey()]);
        Narration::factory()->for($matter)->create(['activity_type_id' => $admin->getKey()]);

        $html = $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('matter.show', $matter))
            ->assertOk()
            ->assertSee('dropdown-item', false)
            ->assertSee('telephone attendance')
            ->assertSee('file administration')
            ->getContent();

        // Each kind is a link in the list, with how many; none is a button of its own in the row.
        $this->assertStringContainsString('filter=CALL', $html);
        $this->assertStringContainsString('filter=ADMIN', $html);
        $this->assertStringNotContainsString('telephone atte…', $html);
    }

    public function test_the_chosen_kind_is_named_on_the_dropdown_button(): void
    {
        $matter = Matter::factory()->create();
        $call = ActivityType::factory()->create(['code' => 'CALL', 'name' => 'telephone attendance']);
        Narration::factory()->for($matter)->count(2)->create(['activity_type_id' => $call->getKey()]);

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('matter.show', [$matter, 'filter' => 'CALL']))
            ->assertSee('telephone attendance 2');
    }
}
