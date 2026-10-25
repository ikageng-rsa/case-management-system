<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Audit;

use App\Enums\Document\DocumentKind;
use App\Models\Audit\Activity;
use App\Models\Client\Client;
use App\Models\Matter\Matter;
use App\Models\Narration\Narration;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_filters_by_actor_and_by_action(): void
    {
        $someone = User::factory()->create();
        Activity::query()->delete();
        $theirs = $this->log('updated', $someone);
        $this->log('created', $someone);
        $this->log('updated');

        $this->assertSame([$theirs->id], Activity::query()->filteredBy($someone->getKey(), 'updated')->pluck('id')->all());
        $this->assertCount(2, Activity::query()->filteredBy($someone->getKey(), null)->get());
        $this->assertCount(3, Activity::query()->filteredBy(null, null)->get());
    }

    public function test_an_entry_belongs_to_the_matter_its_subject_is_on(): void
    {
        Storage::fake(config('media-library.disk_name'));
        $matter = Matter::factory()->create();

        $this->assertTrue($this->log('updated', subject: $matter)->belongingMatter()->is($matter));
        $this->assertTrue($this->log('updated', subject: Narration::factory()->for($matter)->create())->belongingMatter()->is($matter));
        $this->assertTrue($this->log('updated', subject: $matter->addDocument(UploadedFile::fake()->create('mandate.pdf', 5, 'application/pdf'), DocumentKind::Mandate))->belongingMatter()->is($matter));
    }

    public function test_an_entry_about_something_outside_a_matter_has_none(): void
    {
        $this->assertNull($this->log('updated', subject: Client::factory()->create())->belongingMatter());
        $this->assertNull($this->log('exported')->belongingMatter());
    }

    protected function log(string $event, ?User $by = null, ?Model $subject = null): Activity
    {
        $logger = activity()->event($event);

        if ($by) {
            $logger->causedBy($by);
        }

        if ($subject) {
            $logger->performedOn($subject);
        }

        return $logger->log($event);
    }
}
