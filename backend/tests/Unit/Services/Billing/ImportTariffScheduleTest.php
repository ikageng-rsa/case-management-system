<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Billing;

use App\Enums\Billing\TariffAdjustmentKind;
use App\Enums\Billing\TariffScale;
use App\Enums\Narration\ActivityMeasure;
use App\Models\Billing\TariffAdjustment;
use App\Models\Billing\TariffSchedule;
use App\Models\Reference\ActivityType;
use App\Models\User\Role;
use App\Services\Billing\ImportTariffSchedule;
use App\Services\Billing\TariffImportFailed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportTariffScheduleTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = "activity_code,scale_a,scale_b,scale_c,scale_d\n";

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('media-library.disk_name'));

        ActivityType::factory()->measuredIn(ActivityMeasure::Page)->create(['code' => 'DRAFT']);
        ActivityType::factory()->measuredIn(ActivityMeasure::Kilometre)->create(['code' => 'TRAVEL']);
        ActivityType::factory()->measuredIn(ActivityMeasure::Minutes, 15)->create(['code' => 'CONS']);
    }

    protected function import(string $rows, string $date = '2027-04-01'): TariffSchedule
    {
        return (new ImportTariffSchedule)->import(
            'Magistrates\' Courts tariff 2027',
            Carbon::parse($date),
            UploadedFile::fake()->createWithContent('tariff.csv', self::HEADER.$rows),
        );
    }

    /** @return list<string> */
    protected function problemsWith(string $rows): array
    {
        try {
            $this->import($rows);
        } catch (TariffImportFailed $e) {
            return $e->problems;
        }

        $this->fail('The file was accepted.');
    }

    public function test_it_saves_each_line_as_a_draft_with_its_rate_on_each_scale(): void
    {
        $schedule = $this->import("DRAFT,128.00,160,208,\nTRAVEL,6,7.50,9.75,\nCONS,720,900,1170,1500\n");

        $this->assertFalse($schedule->isPublished());
        $this->assertSame('2027-04-01', $schedule->effective_from->toDateString());
        $this->assertCount(3, $schedule->items);

        $travel = $schedule->items->firstWhere('scale_a_cents', 600);
        $this->assertSame(975, $travel->rate(TariffScale::C));
        $this->assertNull($travel->rate(TariffScale::D));
        $this->assertSame(150000, $schedule->items->firstWhere('scale_a_cents', 72000)->rate(TariffScale::D));
    }

    public function test_it_keeps_the_uploaded_file_with_the_schedule(): void
    {
        $this->assertSame('tariff.csv', $this->import("DRAFT,49,,,\n")->source()->file_name);
    }

    public function test_each_role_starts_with_its_markup_on_the_latest_published_tariff(): void
    {
        $candidate = Role::findOrCreate('candidate_attorney');
        $published = TariffSchedule::factory()->startingOn('2026-04-01')->create();
        TariffAdjustment::factory()->for($published, 'schedule')->percent(-50)->create(['role_id' => $candidate->getKey()]);
        TariffAdjustment::factory()->for(TariffSchedule::factory()->startingOn('2026-10-01')->draft()->create(), 'schedule')
            ->amount(99)->create(['role_id' => $candidate->getKey()]);

        $adjustment = $this->import("DRAFT,49,,,\n")->adjustments->sole();

        $this->assertSame($candidate->getKey(), $adjustment->role_id);
        $this->assertSame(TariffAdjustmentKind::Percent, $adjustment->kind);
        $this->assertSame(-5000, $adjustment->value);
    }

    public function test_it_lists_every_problem_by_line_and_saves_nothing(): void
    {
        $problems = $this->problemsWith("NOPE,49,,,\nDRAFT,abc,,,\nTRAVEL,,,,\n");

        $this->assertSame([
            'Line 2: there is no kind of work with the code "NOPE".',
            'Line 3: the scale A rate "abc" is not an amount in rand, such as 49 or 49.50.',
            'Line 4: it has no rate on any scale.',
        ], $problems);
        $this->assertSame(0, TariffSchedule::count());
    }

    public function test_it_refuses_the_same_kind_of_work_twice(): void
    {
        $this->assertSame(['Line 3: DRAFT is already on line 2.'], $this->problemsWith("DRAFT,49,,,\ndraft,50,,,\n"));
    }

    public function test_it_refuses_a_retired_kind_of_work(): void
    {
        ActivityType::where('code', 'DRAFT')->first()->delete();

        $this->assertStringContainsString('DRAFT has been retired', $this->problemsWith("DRAFT,49,,,\n")[0]);
    }

    public function test_it_refuses_a_file_with_no_rates_or_the_wrong_headings(): void
    {
        $this->assertSame(['The file has no rates under its headings.'], $this->problemsWith(''));

        $this->expectException(TariffImportFailed::class);

        (new ImportTariffSchedule)->import('Tariff', Carbon::parse('2027-04-01'), UploadedFile::fake()->createWithContent('t.csv', "activity_code,scale,role,unit_rate\nDRAFT,A,,49\n"));
    }
}
