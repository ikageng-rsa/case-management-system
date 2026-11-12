<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Narration\ActivityMeasure;
use App\Models\Billing\TariffSchedule;
use App\Models\Reference\ActivityType;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TariffScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
        Storage::fake(config('media-library.disk_name'));

        ActivityType::factory()->measuredIn(ActivityMeasure::Page)->create(['code' => 'DRAFT']);
    }

    protected function userWithRole(RoleEnum $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    protected function tariffFile(string $rows): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('tariff.csv', "activity_code,scale,role,unit_rate,time_rate,time_block_minutes\n{$rows}");
    }

    public function test_a_manager_uploads_a_tariff_and_sees_its_rates(): void
    {
        $manager = $this->userWithRole(RoleEnum::Manager);

        $response = $this->actingAs($manager)->post(route('tariff-schedule.store'), [
            'name' => 'Magistrates\' Courts tariff 2027',
            'effective_from' => '2027-04-01',
            'file' => $this->tariffFile("DRAFT,A,attorney,49.00,,\n"),
        ]);

        $schedule = TariffSchedule::sole();
        $response->assertRedirect(route('tariff-schedule.show', $schedule));
        $this->assertSame(4900, $schedule->items->sole()->unit_rate_cents);

        $this->actingAs($manager)->get(route('tariff-schedule.show', $schedule))
            ->assertOk()
            ->assertSee('R 49.00')
            ->assertSee('Attorney');

        $this->actingAs($manager)->get(route('reference-data', ['tab' => 'tariffs']))
            ->assertOk()
            ->assertSee('Magistrates&#039; Courts tariff 2027', false);
    }

    public function test_a_file_with_mistakes_saves_nothing_and_lists_each_line(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Manager))
            ->from(route('reference-data', ['tab' => 'tariffs']))
            ->post(route('tariff-schedule.store'), [
                'name' => 'Tariff 2027',
                'effective_from' => '2027-04-01',
                'file' => $this->tariffFile("NOPE,,,49,,\n"),
            ])
            ->assertRedirect(route('reference-data', ['tab' => 'tariffs']))
            ->assertSessionHasErrorsIn('tariff_schedule_new', 'file');

        $this->assertSame(0, TariffSchedule::count());
    }

    public function test_two_tariffs_cannot_start_on_the_same_day(): void
    {
        TariffSchedule::factory()->startingOn('2027-04-01')->create();

        $this->actingAs($this->userWithRole(RoleEnum::Manager))
            ->post(route('tariff-schedule.store'), [
                'name' => 'Tariff 2027',
                'effective_from' => '2027-04-01',
                'file' => $this->tariffFile("DRAFT,,,49,,\n"),
            ])
            ->assertSessionHasErrorsIn('tariff_schedule_new', 'effective_from');
    }

    public function test_the_template_lists_each_billable_kind_of_work(): void
    {
        ActivityType::factory()->nonBillable()->create(['code' => 'ADMIN']);

        $response = $this->actingAs($this->userWithRole(RoleEnum::Manager))->get(route('tariff-schedule.template'));

        $response->assertOk()->assertDownload('tariff-template.csv');
        $this->assertSame("activity_code,scale,role,unit_rate,time_rate,time_block_minutes\nDRAFT,,,,,\n", $response->getContent());
    }

    public function test_only_an_upcoming_tariff_can_be_removed(): void
    {
        $manager = $this->userWithRole(RoleEnum::Manager);
        $inForce = TariffSchedule::factory()->startingOn(today()->toDateString())->create();
        $upcoming = TariffSchedule::factory()->upcoming()->create();

        $this->actingAs($manager)->delete(route('tariff-schedule.destroy', $inForce))
            ->assertSessionHasErrorsIn('tariff_schedules', 'tariff_schedule');
        $this->actingAs($manager)->delete(route('tariff-schedule.destroy', $upcoming))
            ->assertSessionHasNoErrors();

        $this->assertModelExists($inForce);
        $this->assertModelMissing($upcoming);
    }

    public function test_tariffs_need_the_manage_tariffs_permission(): void
    {
        $secretary = $this->userWithRole(RoleEnum::Secretary);
        $schedule = TariffSchedule::factory()->create();

        $this->actingAs($secretary)->get(route('tariff-schedule.show', $schedule))->assertForbidden();
        $this->actingAs($secretary)->get(route('tariff-schedule.template'))->assertForbidden();
        $this->actingAs($secretary)->post(route('tariff-schedule.store'))->assertForbidden();
    }
}
