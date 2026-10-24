<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\Matters;

use App\Enums\Document\DocumentKind;
use App\Models\DiaryEntry;
use App\Models\Matter;
use App\Models\MatterAssignment;
use App\Models\MatterParty;
use App\Models\Narration;
use App\Queries\Matters\MatterAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class MatterAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_includes_what_was_done_to_the_matter_and_everything_on_it(): void
    {
        Storage::fake(config('media-library.disk_name'));
        $matter = Matter::factory()->create();
        $subjects = [
            $matter,
            Narration::factory()->for($matter)->create(),
            DiaryEntry::factory()->for($matter)->create(),
            MatterAssignment::factory()->for($matter)->create(),
            MatterParty::factory()->for($matter)->create(),
            $matter->addDocument(UploadedFile::fake()->create('mandate.pdf', 5, 'application/pdf'), DocumentKind::Mandate),
        ];
        Activity::query()->delete();

        foreach ($subjects as $subject) {
            $this->logOn($subject);
        }

        $trail = (new MatterAuditTrail)->for($matter);

        $this->assertSame(6, $trail->total());
        $this->assertEqualsCanonicalizing(
            ['matter', 'narration', 'diary_entry', 'matter_assignment', 'matter_party', 'document'],
            collect($trail->items())->pluck('subject_type')->all(),
        );
    }

    public function test_it_leaves_out_another_matters_activity(): void
    {
        $matter = Matter::factory()->create();
        $other = Matter::factory()->create();
        $narration = Narration::factory()->for($other)->create();
        Activity::query()->delete();

        $this->logOn($other);
        $this->logOn($narration);

        $this->assertSame(0, (new MatterAuditTrail)->for($matter)->total());
    }

    public function test_it_lists_the_newest_activity_first(): void
    {
        $matter = Matter::factory()->create();
        Activity::query()->delete();

        $older = $this->logOn($matter);
        $newer = $this->logOn($matter);

        $this->assertSame([$newer->id, $older->id], collect((new MatterAuditTrail)->for($matter)->items())->pluck('id')->all());
    }

    protected function logOn(Model $subject): Activity
    {
        return activity()->performedOn($subject)->event('read')->log('read');
    }
}
