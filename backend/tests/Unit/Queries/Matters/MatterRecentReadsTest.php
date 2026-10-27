<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\Matters;

use App\Models\Matter\Matter;
use App\Models\User\User;
use App\Queries\Matters\MatterRecentReads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class MatterRecentReadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_the_last_three_reads_newest_first(): void
    {
        $matter = Matter::factory()->create();
        $reads = collect(range(1, 4))->map(fn () => $this->logOn($matter, 'read'));

        $recent = (new MatterRecentReads)->for($matter);

        $this->assertSame($reads->reverse()->take(3)->pluck('id')->values()->all(), $recent->pluck('id')->all());
    }

    public function test_it_leaves_out_other_events_and_other_matters(): void
    {
        $matter = Matter::factory()->create();
        Activity::query()->delete();
        $read = $this->logOn($matter, 'read');
        $this->logOn($matter, 'exported');
        $this->logOn(Matter::factory()->create(), 'read');

        $this->assertSame([$read->id], (new MatterRecentReads)->for($matter)->pluck('id')->all());
    }

    public function test_it_still_names_someone_who_has_since_been_deactivated(): void
    {
        $matter = Matter::factory()->create();
        $former = User::factory()->create(['name' => 'Lerato Former']);
        Activity::query()->delete();
        activity()->performedOn($matter)->causedBy($former)->event('read')->log('read');
        $former->delete();

        $this->assertSame('Lerato Former', (new MatterRecentReads)->for($matter)->first()->causer?->name);
    }

    protected function logOn(Matter $matter, string $event): Activity
    {
        return activity()->performedOn($matter)->event($event)->log($event);
    }
}
