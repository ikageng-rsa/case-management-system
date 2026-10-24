<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\Matters;

use App\Enums\Document\DocumentKind;
use App\Models\Matter;
use App\Queries\Matters\MatterDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MatterDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('media-library.disk_name'));
    }

    public function test_it_lists_every_document_on_the_matter_newest_first(): void
    {
        $matter = Matter::factory()->create();
        $this->travelTo(now()->subDay());
        $older = $matter->addDocument(UploadedFile::fake()->create('mandate.pdf', 5, 'application/pdf'), DocumentKind::Mandate);
        $this->travelBack();
        $newer = $matter->addDocument(UploadedFile::fake()->create('letter.pdf', 5, 'application/pdf'), DocumentKind::Correspondence);
        Matter::factory()->create()->addDocument(UploadedFile::fake()->create('other.pdf', 5, 'application/pdf'), DocumentKind::Mandate);

        $documents = (new MatterDocuments)->for($matter, null);

        $this->assertSame([$newer->id, $older->id], $documents['documents']->pluck('id')->all());
        $this->assertSame(2, $documents['allDocumentsCount']);
    }

    public function test_it_narrows_to_one_kind_and_counts_each_kind(): void
    {
        $matter = Matter::factory()->create();
        $mandate = $matter->addDocument(UploadedFile::fake()->create('mandate.pdf', 5, 'application/pdf'), DocumentKind::Mandate);
        $matter->addDocument(UploadedFile::fake()->create('letter.pdf', 5, 'application/pdf'), DocumentKind::Correspondence);
        $matter->addDocument(UploadedFile::fake()->create('reply.pdf', 5, 'application/pdf'), DocumentKind::Correspondence);

        $documents = (new MatterDocuments)->for($matter, DocumentKind::Mandate);

        $this->assertSame([$mandate->id], $documents['documents']->pluck('id')->all());
        $this->assertSame(3, $documents['allDocumentsCount']);
        $this->assertSame(['mandate' => 1, 'correspondence' => 2], $documents['kindCounts']->all());
        $this->assertSame(DocumentKind::Mandate, $documents['kind']);
    }
}
