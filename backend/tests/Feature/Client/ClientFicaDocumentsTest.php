<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Client\FicaDocument;
use App\Models\Client\Client;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientFicaDocumentsTest extends TestCase
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

    private function pdf(string $name = 'id.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 10, 'application/pdf');
    }

    /** A file whose type is read from what is in it, not from its name. */
    private function realFile(string $name, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'fica');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, null, null, true);
    }

    public function test_an_individual_and_an_entity_are_each_asked_for_their_own_documents(): void
    {
        $individual = Client::factory()->create();
        $entity = Client::factory()->entity()->create();

        $this->assertSame([FicaDocument::IdentityDocument, FicaDocument::ProofOfAddress], $individual->outstandingFicaDocuments());
        $this->assertContains(FicaDocument::SignatoryResolution, $entity->outstandingFicaDocuments());
        $this->assertNotContains(FicaDocument::ProofOfAddress, $entity->outstandingFicaDocuments());
    }

    public function test_a_document_can_be_uploaded_on_the_client_and_is_no_longer_outstanding(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('client.fica.store', $client), ['document' => 'identity_document', 'file' => $this->pdf()])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('client.show', $client));

        $client->refresh();

        $this->assertCount(1, $client->ficaDocuments(FicaDocument::IdentityDocument));
        $this->assertSame([FicaDocument::ProofOfAddress], $client->outstandingFicaDocuments());
        $this->assertDatabaseHas('activity_log', ['event' => 'uploaded', 'subject_type' => 'document']);
    }

    public function test_only_the_documents_asked_of_that_kind_of_client_can_be_filed(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('client.fica.store', $client), ['document' => 'signatory_resolution', 'file' => $this->pdf()])
            ->assertSessionHasErrorsIn('fica', 'document');
    }

    public function test_a_file_that_is_not_what_its_name_says_is_refused(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('client.fica.store', $client), [
                'document' => 'identity_document',
                'file' => $this->realFile('id.pdf', '<html><script>alert(1)</script></html>'),
            ])
            ->assertSessionHasErrorsIn('fica', 'file');
    }

    public function test_uploading_needs_the_edit_clients_permission(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->userWithRole(RoleEnum::CandidateAttorney))
            ->post(route('client.fica.store', $client), ['document' => 'identity_document', 'file' => $this->pdf()])
            ->assertForbidden();
    }

    public function test_a_document_is_downloaded_only_by_those_cleared_to_see_identifiers_in_full_and_each_download_is_logged(): void
    {
        $client = Client::factory()->create();
        $media = $client->addFicaDocument($this->pdf(), FicaDocument::IdentityDocument);

        $this->actingAs($this->userWithRole(RoleEnum::Manager))
            ->get(route('client.fica.download', [$client, $media->getKey()]))
            ->assertForbidden();

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->get(route('client.fica.download', [$client, $media->getKey()]))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertDatabaseHas('activity_log', ['event' => 'downloaded', 'subject_id' => (string) $media->getKey()]);
    }

    public function test_a_document_on_another_client_is_not_found(): void
    {
        $client = Client::factory()->create();
        $other = Client::factory()->create();
        $media = $other->addFicaDocument($this->pdf(), FicaDocument::IdentityDocument);

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('client.fica.download', [$client, $media->getKey()]))
            ->assertNotFound();
    }

    public function test_deleting_needs_the_delete_documents_permission(): void
    {
        $client = Client::factory()->create();
        $media = $client->addFicaDocument($this->pdf(), FicaDocument::IdentityDocument);

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->delete(route('client.fica.destroy', [$client, $media->getKey()]))
            ->assertForbidden();

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->delete(route('client.fica.destroy', [$client, $media->getKey()]))
            ->assertRedirect(route('client.show', $client));

        $this->assertCount(0, $client->refresh()->ficaDocuments());
    }

    public function test_documents_can_be_filed_at_intake_and_the_other_kinds_documents_are_ignored(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('client.store'), [
                'type' => 'individual',
                'first_name' => 'Amara',
                'last_name' => 'Okafor',
                'fica' => [
                    'identity_document' => $this->pdf('passport.pdf'),
                    'signatory_resolution' => $this->pdf('resolution.pdf'),
                ],
            ])
            ->assertSessionHasNoErrors();

        $client = Client::firstOrFail();

        $this->assertSame(['passport.pdf'], $client->ficaDocuments()->pluck('file_name')->all());
        $this->assertSame([FicaDocument::ProofOfAddress], $client->outstandingFicaDocuments());
    }

    public function test_a_bad_file_at_intake_registers_nothing(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('client.store'), [
                'type' => 'individual',
                'first_name' => 'Amara',
                'last_name' => 'Okafor',
                'fica' => ['identity_document' => UploadedFile::fake()->create('setup.exe', 10)],
            ])
            ->assertSessionHasErrorsIn('client', 'fica.identity_document');

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_the_client_page_shows_what_is_on_file_and_what_is_outstanding(): void
    {
        $client = Client::factory()->create();
        $client->addFicaDocument($this->pdf('passport.pdf'), FicaDocument::IdentityDocument);

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->get(route('client.show', $client))
            ->assertOk()
            ->assertSee('1 outstanding')
            ->assertSee('passport.pdf')
            ->assertSee(route('client.fica.download', [$client, $client->ficaDocuments()->first()->getKey()]), false)
            ->assertSee('Proof of residential address');

        // Seen, but not opened, by someone not cleared to read identifiers in full.
        $this->actingAs($this->userWithRole(RoleEnum::Manager))
            ->get(route('client.show', $client))
            ->assertSee('passport.pdf')
            ->assertDontSee(route('client.fica.download', [$client, $client->ficaDocuments()->first()->getKey()]), false);
    }
}
