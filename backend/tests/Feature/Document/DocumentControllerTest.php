<?php

declare(strict_types=1);

namespace Tests\Feature\Document;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Document\DocumentKind;
use App\Enums\Matter\Assignment;
use App\Models\Matter;
use App\Models\MatterAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class DocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
        Storage::fake(config('media-library.disk_name'));
    }

    protected function userWithRole(RoleEnum $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    protected function file(string $name, DocumentKind $kind, Matter $matter): void
    {
        $matter->addDocument(UploadedFile::fake()->create($name, 10, 'application/pdf'), $kind);
    }

    public function test_it_lists_the_documents_on_every_matter_for_users_who_view_all(): void
    {
        $one = Matter::factory()->create(['reference' => 'IOT/CIV/1/2026']);
        $two = Matter::factory()->create(['reference' => 'IOT/CIV/2/2026']);
        $this->file('bundle.pdf', DocumentKind::Bundle, $one);
        $this->file('mandate.pdf', DocumentKind::Mandate, $two);

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('documents'))
            ->assertOk()
            ->assertSee('bundle.pdf')
            ->assertSee('mandate.pdf')
            ->assertSee('IOT/CIV/1/2026')
            ->assertViewHas('fileCount', 2);
    }

    public function test_a_user_only_sees_documents_on_matters_they_carry(): void
    {
        $mine = Matter::factory()->create();
        $theirs = Matter::factory()->create();
        $this->file('mine.pdf', DocumentKind::Bundle, $mine);
        $this->file('theirs.pdf', DocumentKind::Bundle, $theirs);

        $attorney = $this->userWithRole(RoleEnum::Attorney);
        MatterAssignment::factory()->for($mine)->for($attorney)->capacity(Assignment::Responsible)->create();

        $this->actingAs($attorney)
            ->get(route('documents'))
            ->assertSee('mine.pdf')
            ->assertDontSee('theirs.pdf')
            ->assertViewHas('fileCount', 1);
    }

    public function test_the_list_filters_by_matter_and_by_kind(): void
    {
        $one = Matter::factory()->create();
        $two = Matter::factory()->create();
        $this->file('bundle.pdf', DocumentKind::Bundle, $one);
        $this->file('evidence.pdf', DocumentKind::Evidence, $one);
        $this->file('other.pdf', DocumentKind::Bundle, $two);

        $director = $this->userWithRole(RoleEnum::Director);

        $this->actingAs($director)
            ->get(route('documents', ['matter' => $one->getKey()]))
            ->assertViewHas('documents', fn ($documents) => $documents->total() === 2);

        $this->actingAs($director)
            ->get(route('documents', ['type' => 'bundle']))
            ->assertViewHas('documents', fn ($documents) => $documents->total() === 2);

        $this->actingAs($director)
            ->get(route('documents', ['matter' => $one->getKey(), 'type' => 'evidence']))
            ->assertViewHas('documents', fn ($documents) => $documents->total() === 1)
            ->assertSee('evidence.pdf')
            ->assertDontSee('other.pdf');
    }

    public function test_it_shows_who_filed_a_document_and_how_often_it_was_downloaded(): void
    {
        $matter = Matter::factory()->create();
        $attorney = $this->userWithRole(RoleEnum::Attorney);
        MatterAssignment::factory()->for($matter)->for($attorney)->capacity(Assignment::Responsible)->create();

        $this->actingAs($attorney)
            ->post(route('matter.documents.store', $matter), [
                'file' => UploadedFile::fake()->create('bundle.pdf', 10, 'application/pdf'),
                'kind' => 'bundle',
            ]);

        $document = $matter->fresh()->getFirstMedia(Matter::DOCUMENTS);

        $this->actingAs($attorney)->get(route('matter.documents.download', [$matter, $document->getKey()]));

        $this->actingAs($attorney)
            ->get(route('documents'))
            ->assertViewHas('uploadedBy', fn ($by) => $by[(string) $document->getKey()]->is($attorney))
            ->assertViewHas('downloads', fn ($downloads) => $downloads[(string) $document->getKey()] === 1);
    }

    public function test_filing_a_document_is_recorded_once_in_the_audit_log(): void
    {
        $matter = Matter::factory()->create();
        $attorney = $this->userWithRole(RoleEnum::Attorney);
        MatterAssignment::factory()->for($matter)->for($attorney)->capacity(Assignment::Responsible)->create();

        $this->actingAs($attorney)->post(route('documents.store'), [
            'matter_id' => $matter->getKey(),
            'file' => UploadedFile::fake()->create('notice.pdf', 10, 'application/pdf'),
            'kind' => 'bundle',
        ]);

        $this->assertSame(1, Activity::query()->where('subject_type', 'document')->where('event', 'uploaded')->count());
    }

    public function test_a_document_can_be_filed_on_a_matter_the_user_carries(): void
    {
        $matter = Matter::factory()->create();
        $attorney = $this->userWithRole(RoleEnum::Attorney);
        MatterAssignment::factory()->for($matter)->for($attorney)->capacity(Assignment::Responsible)->create();

        $this->actingAs($attorney)
            ->post(route('documents.store'), [
                'matter_id' => $matter->getKey(),
                'file' => UploadedFile::fake()->create('notice.pdf', 10, 'application/pdf'),
                'kind' => 'correspondence',
            ])
            ->assertRedirect(route('documents', ['matter' => $matter->getKey()]))
            ->assertSessionHasNoErrors();

        $this->assertSame('correspondence', $matter->fresh()->getFirstMedia(Matter::DOCUMENTS)->getCustomProperty('kind'));
    }

    public function test_a_document_cannot_be_filed_on_a_matter_the_user_does_not_carry_or_that_is_closed(): void
    {
        $stranger = $this->userWithRole(RoleEnum::Attorney);
        $payload = fn (Matter $matter) => [
            'matter_id' => $matter->getKey(),
            'file' => UploadedFile::fake()->create('notice.pdf', 10, 'application/pdf'),
            'kind' => 'report',
        ];

        $notMine = Matter::factory()->create();
        $this->actingAs($stranger)->post(route('documents.store'), $payload($notMine))->assertForbidden();

        $closed = Matter::factory()->closed()->create();
        MatterAssignment::factory()->for($closed)->for($stranger)->capacity(Assignment::Responsible)->create();
        $this->actingAs($stranger)->post(route('documents.store'), $payload($closed))->assertForbidden();

        $this->assertCount(0, $notMine->fresh()->documents());
        $this->assertCount(0, $closed->fresh()->documents());
    }

    public function test_filing_a_document_validates_the_matter_file_and_kind(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->post(route('documents.store'), [])
            ->assertSessionHasErrorsIn('document', ['matter_id', 'file', 'kind']);
    }

    public function test_the_upload_sheet_only_offers_open_matters_the_user_carries(): void
    {
        $open = Matter::factory()->create(['reference' => 'IOT/CIV/1/2026']);
        $closed = Matter::factory()->closed()->create(['reference' => 'IOT/CIV/2/2026']);
        Matter::factory()->create(['reference' => 'IOT/CIV/3/2026']);

        $attorney = $this->userWithRole(RoleEnum::Attorney);
        MatterAssignment::factory()->for($open)->for($attorney)->capacity(Assignment::Responsible)->create();
        MatterAssignment::factory()->for($closed)->for($attorney)->capacity(Assignment::Responsible)->create();

        $this->actingAs($attorney)
            ->get(route('documents'))
            ->assertViewHas('fileableMatters', fn ($matters) => $matters->pluck('reference')->all() === ['IOT/CIV/1/2026']);
    }
}
