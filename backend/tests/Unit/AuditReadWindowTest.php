<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Document\DocumentKind;
use App\Models\Matter\Matter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditReadWindowTest extends TestCase
{
    use RefreshDatabase;

    private function reads(Matter $matter): int
    {
        return Activity::forSubject($matter)->where('event', 'read')->count();
    }

    public function test_a_repeat_read_inside_the_window_is_not_recorded_again(): void
    {
        $this->actingAs(User::factory()->create());
        $matter = Matter::factory()->create();

        $matter->recordAccess();
        $this->travel(59)->minutes();
        $matter->recordAccess();

        $this->assertSame(1, $this->reads($matter));
    }

    public function test_a_read_after_the_window_is_recorded_again(): void
    {
        $this->actingAs(User::factory()->create());
        $matter = Matter::factory()->create();

        $matter->recordAccess();
        $this->travel(61)->minutes();
        $matter->recordAccess();

        $this->assertSame(2, $this->reads($matter));
    }

    public function test_the_window_is_fixed_from_the_first_read_not_extended_by_later_ones(): void
    {
        $this->actingAs(User::factory()->create());
        $matter = Matter::factory()->create();

        $matter->recordAccess();
        $this->travel(40)->minutes();
        $matter->recordAccess();
        $this->travel(30)->minutes();
        $matter->recordAccess();

        $this->assertSame(2, $this->reads($matter));
    }

    public function test_the_window_is_per_person(): void
    {
        $matter = Matter::factory()->create();

        $this->actingAs(User::factory()->create());
        $matter->recordAccess();

        $this->actingAs(User::factory()->create());
        $matter->recordAccess();

        $this->assertSame(2, $this->reads($matter));
    }

    public function test_the_window_is_per_record(): void
    {
        $this->actingAs(User::factory()->create());
        [$one, $two] = Matter::factory()->count(2)->create()->all();

        $one->recordAccess();
        $two->recordAccess();

        $this->assertSame(1, $this->reads($one));
        $this->assertSame(1, $this->reads($two));
    }

    public function test_the_window_length_is_configurable(): void
    {
        config(['audit.read_window_minutes' => 5]);

        $this->actingAs(User::factory()->create());
        $matter = Matter::factory()->create();

        $matter->recordAccess();
        $this->travel(6)->minutes();
        $matter->recordAccess();

        $this->assertSame(2, $this->reads($matter));
    }

    public function test_downloads_and_exports_are_recorded_every_time(): void
    {
        Storage::fake(config('media-library.disk_name'));
        $this->actingAs(User::factory()->create());
        $matter = Matter::factory()->create();
        $document = $matter->addDocument(UploadedFile::fake()->create('bundle.pdf', 5, 'application/pdf'), DocumentKind::Bundle);

        $document->recordAccess('downloaded');
        $document->recordAccess('downloaded');
        $matter->recordAccess('exported');
        $matter->recordAccess('exported');

        $this->assertSame(2, Activity::forSubject($document)->where('event', 'downloaded')->count());
        $this->assertSame(2, Activity::forSubject($matter)->where('event', 'exported')->count());
    }
}
