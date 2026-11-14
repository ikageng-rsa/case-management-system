<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Document;

use App\Enums\Auth\Role;
use App\Enums\Document\DocumentKind;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\User\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Api\ApiTestCase;

class MatterDocumentTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('media-library.disk_name'));
    }

    public function test_documents_need_a_token(): void
    {
        $matter = Matter::factory()->create();

        $this->getJson("/api/v1/matters/{$matter->id}/documents")->assertJsonApiError(401);
        $this->postJson("/api/v1/matters/{$matter->id}/documents")->assertJsonApiError(401);
    }

    public function test_a_matters_documents_are_listed_newest_first_with_counts_by_kind(): void
    {
        $this->signInAs(Role::Secretary);
        $matter = Matter::factory()->create();
        $this->travelTo(now()->subDay());
        $older = $this->file($matter, 'mandate.pdf', DocumentKind::Mandate);
        $this->travelBack();
        $newer = $this->file($matter, 'order.pdf', DocumentKind::CourtOrder);
        $this->file(Matter::factory()->create(), 'elsewhere.pdf', DocumentKind::Mandate);

        $response = $this->getJson("/api/v1/matters/{$matter->id}/documents")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'documents')
            ->assertJsonPath('data.0.id', (string) $newer->id)
            ->assertJsonPath('data.0.attributes.file_name', 'order.pdf')
            ->assertJsonPath('data.0.attributes.kind', 'court_order')
            ->assertJsonPath('data.1.id', (string) $older->id)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.counts.all', 2)
            ->assertJsonPath('meta.counts.kinds', ['mandate' => 1, 'court_order' => 1]);

        $this->assertStringNotContainsString('storage', $response->getContent());
    }

    public function test_the_list_is_narrowed_to_one_kind(): void
    {
        $this->signInAs(Role::Secretary);
        $matter = Matter::factory()->create();
        $mandate = $this->file($matter, 'mandate.pdf', DocumentKind::Mandate);
        $this->file($matter, 'order.pdf', DocumentKind::CourtOrder);

        $this->getJson("/api/v1/matters/{$matter->id}/documents?kind=mandate")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $mandate->id);

        $this->getJson("/api/v1/matters/{$matter->id}/documents?kind=letter")->assertJsonApiError(422, '/kind');
    }

    public function test_a_document_is_downloaded_and_the_download_logged_as_from_the_app(): void
    {
        $user = $this->signInAs(Role::Secretary);
        $matter = Matter::factory()->create();
        $document = $this->file($matter, 'bundle.pdf', DocumentKind::Bundle);

        $this->get("/api/v1/matters/{$matter->id}/documents/{$document->id}")
            ->assertOk()
            ->assertDownload('bundle.pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $log = Activity::forSubject($document)->where('event', 'downloaded')->sole();

        $this->assertTrue($log->causer->is($user));
        $this->assertSame('api', $log->getProperty('via'));
    }

    public function test_a_document_on_another_matter_or_a_matter_the_user_may_not_see_is_not_found(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user);
        $other = Matter::factory()->create();
        $secret = $this->file($other, 'secret.pdf', DocumentKind::Evidence);

        $this->getJson("/api/v1/matters/{$matter->id}/documents/{$secret->id}")->assertJsonApiError(404);
        $this->getJson("/api/v1/matters/{$other->id}/documents/{$secret->id}")->assertJsonApiError(404);
        $this->getJson("/api/v1/matters/{$other->id}/documents")->assertJsonApiError(404);
        $this->assertSame(0, Activity::query()->where('event', 'downloaded')->count());
    }

    public function test_someone_carrying_the_matter_files_a_document(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user);

        $response = $this->post("/api/v1/matters/{$matter->id}/documents", [
            'file' => UploadedFile::fake()->create('scan.pdf', 20, 'application/pdf'),
            'kind' => 'evidence',
            'name' => 'Photo of the accident scene',
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'documents')
            ->assertJsonPath('data.attributes.name', 'Photo of the accident scene')
            ->assertJsonPath('data.attributes.file_name', 'scan.pdf')
            ->assertJsonPath('data.attributes.kind', 'evidence');

        $document = $matter->fresh()->getFirstMedia(Matter::DOCUMENTS);

        $this->assertSame((string) $document->id, $response->json('data.id'));
        $this->assertSame('api', Activity::forSubject($document)->where('event', 'uploaded')->sole()->getProperty('via'));
    }

    public function test_without_a_name_the_document_is_listed_by_its_file(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user);

        $this->post("/api/v1/matters/{$matter->id}/documents", [
            'file' => UploadedFile::fake()->create('letter-of-demand.pdf', 20, 'application/pdf'),
            'kind' => 'correspondence',
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.attributes.name', 'letter-of-demand');
    }

    public function test_the_file_is_checked_the_way_the_web_checks_it(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user);

        $this->post("/api/v1/matters/{$matter->id}/documents", [], ['Accept' => 'application/json'])
            ->assertJsonApiError(422, '/file')
            ->assertJsonApiError(422, '/kind');

        $this->post("/api/v1/matters/{$matter->id}/documents", [
            'file' => UploadedFile::fake()->create('run.exe', 20, 'application/x-msdownload'),
            'kind' => 'evidence',
        ], ['Accept' => 'application/json'])->assertJsonApiError(422, '/file');

        $this->assertCount(0, $matter->fresh()->documents());
    }

    public function test_only_those_carrying_an_open_matter_may_file_on_it(): void
    {
        $this->signInAs(Role::Secretary);
        $seen = Matter::factory()->create();
        $upload = fn () => ['file' => UploadedFile::fake()->create('a.pdf', 5, 'application/pdf'), 'kind' => 'evidence'];

        $this->post("/api/v1/matters/{$seen->id}/documents", $upload(), ['Accept' => 'application/json'])->assertJsonApiError(403);

        $user = $this->signInAs(Role::Attorney);
        $closed = $this->carriedBy($user, Matter::factory()->closed()->create());

        $this->post("/api/v1/matters/{$closed->id}/documents", $upload(), ['Accept' => 'application/json'])->assertJsonApiError(403);
        $this->assertCount(0, $seen->fresh()->documents());
    }

    private function file(Matter $matter, string $name, DocumentKind $kind)
    {
        return $matter->addDocument(UploadedFile::fake()->create($name, 10, 'application/pdf'), $kind);
    }

    private function carriedBy(User $user, ?Matter $matter = null): Matter
    {
        $matter ??= Matter::factory()->create();
        MatterAssignment::factory()->for($matter)->for($user)->create();

        return $matter;
    }
}
