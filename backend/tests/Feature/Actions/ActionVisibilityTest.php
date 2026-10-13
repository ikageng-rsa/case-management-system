<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Document\DocumentKind;
use App\Enums\Matter\Assignment;
use App\Models\Matter;
use App\Models\MatterAssignment;
use App\Models\MatterParty;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/*
 * A control is only offered to someone who could use it. These pin each one to
 * the rule the server applies to the action behind it, so a button never leads
 * to a refusal.
 */
class ActionVisibilityTest extends TestCase
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

    protected function carrying(Matter $matter, RoleEnum $role): User
    {
        $user = $this->userWithRole($role);

        MatterAssignment::factory()->for($matter)->for($user)->capacity(Assignment::Assisting)->create();

        return $user;
    }

    /** The triggers that open a sheet, as the page writes them. */
    protected function opens(string $sheet): string
    {
        return "sheet-open', '{$sheet}'";
    }

    public function test_the_work_buttons_follow_being_assigned_to_an_open_matter(): void
    {
        $matter = Matter::factory()->create();
        $assigned = $this->carrying($matter, RoleEnum::Attorney);
        $notAssigned = $this->userWithRole(RoleEnum::Director);

        $this->actingAs($assigned)->get(route('matter.show', $matter))
            ->assertSee($this->opens('new-narration'), false)
            ->assertSee($this->opens('new-diary-entry'), false)
            ->assertSee($this->opens('upload-document'), false);

        $this->actingAs($notAssigned)->get(route('matter.show', $matter))
            ->assertOk()
            ->assertDontSee($this->opens('new-narration'), false)
            ->assertDontSee($this->opens('new-diary-entry'), false)
            ->assertDontSee($this->opens('upload-document'), false)
            ->assertDontSee('action="'.route('matter.narrations.store', $matter).'"', false);
    }

    public function test_nothing_is_offered_on_a_closed_matter(): void
    {
        $matter = Matter::factory()->closed()->create();
        MatterParty::factory()->for($matter)->create();
        $director = $this->userWithRole(RoleEnum::Director);
        $assigned = $this->carrying($matter, RoleEnum::Attorney);

        foreach ([$director, $assigned] as $user) {
            $this->actingAs($user)->get(route('matter.show', $matter))
                ->assertDontSee($this->opens('new-narration'), false)
                ->assertDontSee($this->opens('assign-member'), false);

            $this->actingAs($user)->get(route('matter.show', [$matter, 'tab' => 'parties']))
                ->assertDontSee($this->opens('new-party'), false)
                ->assertDontSee('edit-party-', false);
        }
    }

    public function test_adding_a_team_member_follows_the_assign_permission(): void
    {
        $matter = Matter::factory()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Director))->get(route('matter.show', $matter))
            ->assertSee($this->opens('assign-member'), false);

        $this->actingAs($this->carrying($matter, RoleEnum::CandidateAttorney))->get(route('matter.show', $matter))
            ->assertDontSee($this->opens('assign-member'), false)
            ->assertDontSee('action="'.route('matter.assign', $matter).'"', false);
    }

    public function test_the_party_controls_follow_the_edit_matters_permission(): void
    {
        $matter = Matter::factory()->create();
        MatterParty::factory()->for($matter)->create();

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->get(route('matter.show', [$matter, 'tab' => 'parties']))
            ->assertSee($this->opens('new-party'), false)
            ->assertSee('edit-party-', false);

        $this->actingAs($this->carrying($matter, RoleEnum::CandidateAttorney))
            ->get(route('matter.show', [$matter, 'tab' => 'parties']))
            ->assertOk()
            ->assertDontSee($this->opens('new-party'), false)
            ->assertDontSee('edit-party-', false);
    }

    public function test_deleting_a_document_follows_its_own_permission(): void
    {
        $matter = Matter::factory()->create();
        $matter->addDocument(
            UploadedFile::fake()->create('a.pdf', 5, 'application/pdf'),
            DocumentKind::Evidence,
        );

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('matter.show', [$matter, 'tab' => 'documents']))
            ->assertSee('data-confirm-title="Delete document"', false);

        $this->actingAs($this->carrying($matter, RoleEnum::Attorney))
            ->get(route('matter.show', [$matter, 'tab' => 'documents']))
            ->assertDontSee('data-confirm-title="Delete document"', false);
    }

    public function test_the_documents_page_only_offers_upload_to_someone_with_a_matter_to_file_on(): void
    {
        $open = Matter::factory()->create();
        $closed = Matter::factory()->closed()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Director))->get(route('documents'))
            ->assertOk()
            ->assertDontSee($this->opens('upload-document'), false)
            ->assertViewHas('canUpload', false);

        $this->actingAs($this->carrying($closed, RoleEnum::Attorney))->get(route('documents'))
            ->assertDontSee($this->opens('upload-document'), false);

        $this->actingAs($this->carrying($open, RoleEnum::Attorney))->get(route('documents'))
            ->assertSee($this->opens('upload-document'), false)
            ->assertViewHas('canUpload', true);
    }

    public function test_the_clients_link_follows_the_view_clients_permission(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Messenger))->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('href="'.route('clients').'"', false);

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))->get(route('dashboard'))
            ->assertSee('href="'.route('clients').'"', false);
    }

    public function test_the_matters_page_offers_a_new_matter_only_to_those_who_may_open_one(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Secretary))->get(route('matters'))
            ->assertSee($this->opens('new-matter'), false);

        $this->actingAs($this->userWithRole(RoleEnum::Manager))->get(route('matters'))
            ->assertDontSee($this->opens('new-matter'), false);
    }
}
