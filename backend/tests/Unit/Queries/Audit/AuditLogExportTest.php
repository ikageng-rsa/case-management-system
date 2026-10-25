<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\Audit;

use App\Models\Audit\Activity;
use App\Models\Matter\Matter;
use App\Models\User\User;
use App\Queries\Audit\AuditLogExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_row_says_when_who_what_and_on_which_matter(): void
    {
        $lerato = User::factory()->create(['name' => 'Lerato']);
        $matter = Matter::factory()->create();
        Activity::query()->delete();
        $entry = activity()->causedBy($lerato)->performedOn($matter)->event('updated')
            ->withProperties(['ip' => '10.0.0.1'])->log('updated the matter');

        $this->assertSame([[
            $entry->created_at->format('Y-m-d H:i:s'),
            'Lerato',
            'updated',
            'updated the matter',
            $matter->reference,
            '10.0.0.1',
        ]], (new AuditLogExport)->for(null, null)->all());
    }

    public function test_it_carries_the_filter_and_names_the_system_when_no_one_acted(): void
    {
        Activity::query()->delete();
        activity()->event('created')->log('created');
        activity()->event('updated')->log('updated');

        $rows = (new AuditLogExport)->for(null, 'created')->all();

        $this->assertCount(1, $rows);
        $this->assertSame(['System', 'created'], [$rows[0][1], $rows[0][2]]);
    }
}
