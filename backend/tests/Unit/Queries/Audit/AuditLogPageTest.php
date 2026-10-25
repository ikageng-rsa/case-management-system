<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\Audit;

use App\Models\Audit\Activity;
use App\Models\Matter\Matter;
use App\Models\User\User;
use App\Queries\Audit\AuditLogPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_pages_eight_at_a_time_newest_first(): void
    {
        Activity::query()->delete();
        $entries = collect(range(1, 9))->map(fn () => activity()->event('created')->log('created'));

        $activities = (new AuditLogPage)->for(null, null)['activities'];

        $this->assertSame(9, $activities->total());
        $this->assertSame($entries->reverse()->take(8)->pluck('id')->values()->all(), $activities->getCollection()->pluck('id')->all());
    }

    public function test_the_filters_offer_everyone_who_acted_and_every_event(): void
    {
        $lerato = User::factory()->create(['name' => 'Lerato']);
        $anele = User::factory()->create(['name' => 'Anele']);
        User::factory()->create(['name' => 'Never Acted']);
        Activity::query()->delete();
        activity()->causedBy($lerato)->event('updated')->log('updated');
        activity()->causedBy($anele)->event('created')->log('created');

        $page = (new AuditLogPage)->for(null, null);

        $this->assertSame(['Anele', 'Lerato'], $page['actors']->pluck('name')->all());
        $this->assertSame(['created', 'updated'], $page['actions']->all());
    }

    public function test_each_entry_carries_its_detail_in_words(): void
    {
        $matter = Matter::factory()->create();
        Activity::query()->delete();
        $entry = activity()->performedOn($matter)->event('field_changed')
            ->withProperties(['ip' => '10.0.0.1', 'field' => 'prescribes_at', 'notified' => true, 'tags' => []])
            ->log('changed a field');

        $detail = (new AuditLogPage)->for(null, null)['details'][$entry->id];

        $this->assertSame('System', $detail['actor']);
        $this->assertSame('field changed', $detail['event']);
        $this->assertSame($matter->reference, $detail['matter']);
        $this->assertSame('10.0.0.1', $detail['ip']);
        // MySQL keeps a JSON object's keys in its own order, so the order is not part of the detail.
        $this->assertEqualsCanonicalizing([
            ['key' => 'field', 'value' => 'prescribes at'],
            ['key' => 'notified', 'value' => 'yes'],
            ['key' => 'tags', 'value' => '—'],
        ], $detail['properties']);
    }
}
