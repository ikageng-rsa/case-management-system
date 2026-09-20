<?php

namespace Tests\Unit\Models;

use App\Enums\Document\DocumentKind;
use App\Models\Matter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class MatterDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documents');
    }

    public function test_it_files_a_document_against_the_matter(): void
    {
        $matter = Matter::factory()->create();

        $media = $matter->addDocument(
            UploadedFile::fake()->createWithContent('mandate.pdf', 'contents'),
            DocumentKind::Mandate,
        );

        $this->assertInstanceOf(Media::class, $media);
        $this->assertCount(1, $matter->refresh()->documents());
        $this->assertSame('mandate.pdf', $media->file_name);
    }

    public function test_it_stores_documents_on_the_private_disk(): void
    {
        $matter = Matter::factory()->create();

        $media = $matter->addDocument(
            UploadedFile::fake()->create('mandate.pdf'),
            DocumentKind::Mandate,
        );

        $this->assertSame('documents', $media->disk);
        $this->assertNotSame('public', $media->disk);
        Storage::disk('documents')->assertExists("{$media->id}/mandate.pdf");
    }

    public function test_it_records_the_kind_as_a_custom_property(): void
    {
        $matter = Matter::factory()->create();

        $media = $matter->addDocument(
            UploadedFile::fake()->create('order.pdf'),
            DocumentKind::CourtOrder,
        );

        $this->assertSame('court_order', $media->getCustomProperty('kind'));
    }

    public function test_it_filters_documents_by_kind(): void
    {
        $matter = Matter::factory()->create();

        $matter->addDocument(UploadedFile::fake()->create('mandate.pdf'), DocumentKind::Mandate);
        $matter->addDocument(UploadedFile::fake()->create('order.pdf'), DocumentKind::CourtOrder);
        $matter->addDocument(UploadedFile::fake()->create('order-2.pdf'), DocumentKind::CourtOrder);

        $matter->refresh();

        $this->assertCount(3, $matter->documents());
        $this->assertCount(2, $matter->documentsOfKind(DocumentKind::CourtOrder));
        $this->assertCount(1, $matter->documentsOfKind(DocumentKind::Mandate));
        $this->assertCount(0, $matter->documentsOfKind(DocumentKind::Evidence));
    }

    public function test_it_keeps_documents_when_the_matter_is_soft_deleted(): void
    {
        $matter = Matter::factory()->create();
        $matter->addDocument(UploadedFile::fake()->create('mandate.pdf'), DocumentKind::Mandate);

        $matter->delete();

        $this->assertSame(1, Media::query()->count());
    }

    public function test_it_deletes_documents_when_the_matter_is_force_deleted(): void
    {
        $matter = Matter::factory()->create();
        $media = $matter->addDocument(UploadedFile::fake()->create('mandate.pdf'), DocumentKind::Mandate);
        $path = "{$media->id}/mandate.pdf";

        $matter->forceDelete();

        $this->assertSame(0, Media::query()->count());
        Storage::disk('documents')->assertMissing($path);
    }

    public function test_it_stores_the_morph_alias_rather_than_the_class_name(): void
    {
        $matter = Matter::factory()->create();

        $media = $matter->addDocument(
            UploadedFile::fake()->create('mandate.pdf'),
            DocumentKind::Mandate,
        );

        $this->assertSame('matter', $media->model_type);
    }
}
