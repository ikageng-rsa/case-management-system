<?php

declare(strict_types=1);

namespace Tests\Feature\Document;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Document\DocumentKind;
use App\Enums\Matter\Assignment;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentAllowListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
        Storage::fake(config('media-library.disk_name'));
    }

    /** @return array{0: User, 1: Matter} someone carrying a matter, and the matter */
    protected function carrier(): array
    {
        $matter = Matter::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(RoleEnum::Attorney->value);
        MatterAssignment::factory()->for($matter)->for($user)->capacity(Assignment::Responsible)->create();

        return [$user, $matter];
    }

    /** A file that is what its contents say, read from disk the way a real upload is. */
    protected function realFile(string $name, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'doc');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, null, null, true);
    }

    protected function file(User $user, Matter $matter, UploadedFile $file)
    {
        return $this->actingAs($user)->post(route('matter.documents.store', $matter), ['file' => $file, 'kind' => 'evidence']);
    }

    public function test_the_kinds_of_file_a_firm_files_are_accepted(): void
    {
        [$user, $matter] = $this->carrier();

        foreach (['bundle.pdf', 'letter.docx', 'schedule.xlsx', 'deck.pptx', 'photo.jpg', 'scan.PNG', 'email.eml', 'call.mp3', 'notes.txt'] as $name) {
            $this->file($user, $matter, UploadedFile::fake()->create($name, 5, $this->declaredTypeFor($name)))
                ->assertSessionHasNoErrors();
        }

        $this->assertCount(9, $matter->fresh()->documents());
    }

    private function declaredTypeFor(string $name): string
    {
        return config('documents.allowed')[strtolower(pathinfo($name, PATHINFO_EXTENSION))][0];
    }

    public function test_programs_scripts_and_formats_that_can_run_code_are_refused(): void
    {
        [$user, $matter] = $this->carrier();

        foreach (['setup.exe', 'run.php', 'page.html', 'image.svg', 'macro.docm', 'sheet.xlsm', 'tool.bat', 'archive.zip', 'noextension'] as $name) {
            $this->file($user, $matter, UploadedFile::fake()->create($name, 5, 'application/octet-stream'))
                ->assertSessionHasErrorsIn('document', 'file');
        }

        $this->assertCount(0, $matter->fresh()->documents());
    }

    public function test_a_file_is_judged_by_its_contents_and_not_its_name(): void
    {
        [$user, $matter] = $this->carrier();

        // Script in a .pdf's clothes.
        $this->file($user, $matter, $this->realFile('notice.pdf', '<html><script>alert(1)</script></html>'))
            ->assertSessionHasErrorsIn('document', 'file');

        // A program with a document's name.
        $this->file($user, $matter, $this->realFile('letter.docx', "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xff\xff"))
            ->assertSessionHasErrorsIn('document', 'file');

        // The real thing.
        $this->file($user, $matter, $this->realFile('notice.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF"))
            ->assertSessionHasNoErrors();

        $this->assertCount(1, $matter->fresh()->documents());
    }

    public function test_the_firm_wide_upload_follows_the_same_list(): void
    {
        [$user, $matter] = $this->carrier();

        $this->actingAs($user)
            ->post(route('documents.store'), [
                'matter_id' => $matter->getKey(),
                'file' => UploadedFile::fake()->create('virus.exe', 5, 'application/x-msdownload'),
                'kind' => 'evidence',
            ])
            ->assertSessionHasErrorsIn('document', 'file');
    }

    public function test_the_size_limit_still_applies(): void
    {
        [$user, $matter] = $this->carrier();

        $this->file($user, $matter, UploadedFile::fake()->create('huge.pdf', 51201, 'application/pdf'))
            ->assertSessionHasErrorsIn('document', 'file');
    }

    public function test_the_list_is_the_firms_to_extend_through_config(): void
    {
        [$user, $matter] = $this->carrier();

        $this->file($user, $matter, UploadedFile::fake()->create('plan.dwg', 5, 'image/vnd.dwg'))->assertSessionHasErrorsIn('document', 'file');

        config(['documents.allowed' => config('documents.allowed') + ['dwg' => ['image/vnd.dwg']]]);

        $this->file($user, $matter, UploadedFile::fake()->create('plan.dwg', 5, 'image/vnd.dwg'))->assertSessionHasNoErrors();
    }

    public function test_the_upload_forms_offer_only_what_is_welcome(): void
    {
        [$user, $matter] = $this->carrier();

        $this->actingAs($user)->get(route('matter.show', $matter))
            ->assertSee('accept=".pdf,.doc,.docx', false)
            ->assertSee('PDF, Office, image, email or audio files, up to 50 MB.');

        $this->actingAs($user)->get(route('documents'))
            ->assertSee('accept=".pdf,.doc,.docx', false);
    }

    public function test_a_download_cannot_be_reinterpreted_by_the_browser(): void
    {
        [$user, $matter] = $this->carrier();
        $document = $matter->addDocument(UploadedFile::fake()->create('bundle.pdf', 5, 'application/pdf'), DocumentKind::Bundle);

        $this->actingAs($user)
            ->get(route('matter.documents.download', [$matter, $document->getKey()]))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
