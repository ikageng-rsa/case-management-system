<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Billing;

use App\Enums\Billing\TariffScale;
use App\Enums\Narration\ActivityMeasure;
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

    private const HEADER = "activity_code,scale,role,unit_rate,time_rate,time_block_minutes\n";

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('media-library.disk_name'));

        ActivityType::factory()->measuredIn(ActivityMeasure::Page)->create(['code' => 'DRAFT']);
        ActivityType::factory()->measuredIn(ActivityMeasure::Kilometre)->create(['code' => 'TRAVEL']);
        ActivityType::factory()->measuredIn(ActivityMeasure::Minutes, 15)->create(['code' => 'CONS']);
        Role::findOrCreate('candidate_attorney');
    }

    protected function import(string $rows): TariffSchedule
    {
        return (new ImportTariffSchedule)->import(
            'Magistrates\' Courts tariff 2027',
            Carbon::parse('2027-04-01'),
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

    public function test_it_saves_each_rate_with_its_scale_role_and_time_charge(): void
    {
        $schedule = $this->import("DRAFT,A,candidate_attorney,49.00,,\nDRAFT,A,,52,,\nTRAVEL,,,10.50,223,15\n");

        $this->assertSame('2027-04-01', $schedule->effective_from->toDateString());
        $this->assertCount(3, $schedule->items);

        $draft = $schedule->items->firstWhere('role_id', Role::findByName('candidate_attorney')->getKey());
        $this->assertSame(TariffScale::A, $draft->scale);
        $this->assertSame(4900, $draft->unit_rate_cents);

        $travel = $schedule->items->firstWhere('unit_rate_cents', 1050);
        $this->assertNull($travel->role_id);
        $this->assertSame(22300, $travel->time_rate_cents);
        $this->assertSame(15, $travel->time_block_minutes);
    }

    public function test_it_keeps_the_uploaded_file_with_the_schedule(): void
    {
        $schedule = $this->import("DRAFT,,,49,,\n");

        $this->assertSame('tariff.csv', $schedule->source()->file_name);
    }

    public function test_it_lists_every_problem_by_line_and_saves_nothing(): void
    {
        $problems = $this->problemsWith("NOPE,,,49,,\nDRAFT,E,,49,,\nDRAFT,,partner,abc,,\n");

        $this->assertCount(3, $problems);
        $this->assertStringStartsWith('Line 2: there is no kind of work with the code "NOPE"', $problems[0]);
        $this->assertStringContainsString('Line 3: the scale "E"', $problems[1]);
        $this->assertStringContainsString('there is no role called "partner"', $problems[2]);
        $this->assertStringContainsString('the unit rate "abc"', $problems[2]);
        $this->assertSame(0, TariffSchedule::count());
    }

    public function test_it_refuses_the_same_rate_twice(): void
    {
        $problems = $this->problemsWith("DRAFT,A,,49,,\ndraft,a,,50,,\n");

        $this->assertSame(['Line 3: it repeats the rate on line 2 for the same kind of work, scale and role.'], $problems);
    }

    public function test_a_time_rate_needs_its_block_and_work_already_counted_in_minutes_takes_none(): void
    {
        $problems = $this->problemsWith("TRAVEL,,,10.50,223,\nCONS,,,500,100,15\n");

        $this->assertStringContainsString('a time rate and its block of minutes go together', $problems[0]);
        $this->assertStringContainsString('CONS is already counted in minutes', $problems[1]);
    }

    public function test_it_refuses_a_retired_kind_of_work(): void
    {
        ActivityType::where('code', 'DRAFT')->first()->delete();

        $this->assertStringContainsString('DRAFT has been retired', $this->problemsWith("DRAFT,,,49,,\n")[0]);
    }

    public function test_it_refuses_a_file_with_no_rates_or_the_wrong_headings(): void
    {
        $this->assertSame(['The file has no rates under its headings.'], $this->problemsWith(''));

        $this->expectException(TariffImportFailed::class);

        (new ImportTariffSchedule)->import('Tariff', Carbon::parse('2027-04-01'), UploadedFile::fake()->createWithContent('t.csv', "code,rate\nDRAFT,49\n"));
    }
}
