<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Narration\ActivityMeasure;
use App\Models\Billing\TariffSchedule;
use App\Models\Reference\ActivityType;
use App\Models\User\Role;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TariffScheduleTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = "activity_code,scale_a,scale_b,scale_c,scale_d,time_rate,time_block_minutes\n";

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
        Storage::fake(config('media-library.disk_name'));

        ActivityType::factory()->measuredIn(ActivityMeasure::Minutes, 30)->create(['code' => 'APP', 'name' => 'Court appearance']);
        ActivityType::factory()->measuredIn(ActivityMeasure::Kilometre)->create(['code' => 'TRAVEL', 'name' => 'Travelling']);
    }

    protected function manager(): User
    {
        return tap(User::factory()->create())->assignRole(RoleEnum::Manager->value);
    }

    protected function upload(User $user, string $rows, string $date = '2027-04-01'): TestResponse
    {
        return $this->actingAs($user)
            ->from(route('reference-data', ['tab' => 'tariffs']))
            ->post(route('tariff-schedule.store'), [
                'name' => 'Magistrates\' Courts tariff 2027',
                'effective_from' => $date,
                'file' => UploadedFile::fake()->createWithContent('tariff.csv', self::HEADER.$rows),
            ]);
    }

    public function test_an_upload_is_read_into_a_draft_to_check_mark_up_and_publish(): void
    {
        $manager = $this->manager();
        $candidate = Role::findByName(RoleEnum::CandidateAttorney->value);
        $director = Role::findByName(RoleEnum::Director->value);

        $this->upload($manager, "APP,1440.00,1800.00,2340.00,,,\nTRAVEL,6.00,7.50,9.75,,1760.00,60\n");

        $schedule = TariffSchedule::sole();
        $this->assertFalse($schedule->isPublished());
        $this->assertNull(TariffSchedule::inForceOn($schedule->effective_from));

        $this->actingAs($manager)->get(route('tariff-schedule.show', $schedule))
            ->assertOk()
            ->assertSee('Draft')
            ->assertSeeInOrder(['Court appearance', 'R 1 440.00', 'R 1 800.00', 'R 2 340.00']);

        $this->actingAs($manager)->put(route('tariff-adjustments.update', $schedule), [
            'adjustments' => [
                $candidate->getKey() => ['kind' => 'percent', 'value' => '-50'],
                $director->getKey() => ['kind' => 'amount', 'value' => '654.55'],
            ],
        ])->assertRedirect(route('tariff-schedule.show', $schedule));

        $this->actingAs($manager)->get(route('tariff-schedule.show', [$schedule, 'role' => $candidate->getKey()]))
            ->assertSeeInOrder(['Court appearance', 'R 720.00', 'R 900.00', 'R 1 170.00'])
            ->assertSee('R 880.00');
        $this->actingAs($manager)->get(route('tariff-schedule.show', [$schedule, 'role' => $director->getKey()]))
            ->assertSee('R 2 094.55');

        $this->actingAs($manager)->post(route('tariff-schedule.publish', $schedule))->assertRedirect();

        $this->assertTrue(TariffSchedule::inForceOn($schedule->effective_from)->is($schedule));
    }

    public function test_a_file_with_mistakes_saves_nothing_and_lists_each_line(): void
    {
        $this->upload($this->manager(), "NOPE,49,,,,,\n")
            ->assertRedirect(route('reference-data', ['tab' => 'tariffs']))
            ->assertSessionHasErrorsIn('tariff_schedule_new', 'file');

        $this->assertSame(0, TariffSchedule::count());
    }

    public function test_two_tariffs_cannot_start_on_the_same_day(): void
    {
        TariffSchedule::factory()->startingOn('2027-04-01')->create();

        $this->upload($this->manager(), "APP,1440,,,,,\n")->assertSessionHasErrorsIn('tariff_schedule_new', 'effective_from');
    }

    public function test_a_markup_needs_an_amount(): void
    {
        $schedule = TariffSchedule::factory()->draft()->create();
        $role = Role::findByName(RoleEnum::Attorney->value);

        $this->actingAs($this->manager())
            ->put(route('tariff-adjustments.update', $schedule), ['adjustments' => [$role->getKey() => ['kind' => 'percent', 'value' => '']]])
            ->assertSessionHasErrorsIn('tariff_adjustments');

        $this->assertSame(0, $schedule->adjustments()->count());
    }

    public function test_the_template_lists_each_billable_kind_of_work(): void
    {
        ActivityType::factory()->nonBillable()->create(['code' => 'ADMIN']);

        $response = $this->actingAs($this->manager())->get(route('tariff-schedule.template'));

        $response->assertOk()->assertDownload('tariff-template.csv');
        $this->assertSame(self::HEADER."APP,,,,,,\nTRAVEL,,,,,,\n", $response->getContent());
    }

    public function test_a_tariff_in_force_cannot_be_removed_but_a_draft_or_upcoming_one_can(): void
    {
        $manager = $this->manager();
        $inForce = TariffSchedule::factory()->startingOn(today()->toDateString())->create();
        $draft = TariffSchedule::factory()->startingOn(today()->subYear()->toDateString())->draft()->create();
        $upcoming = TariffSchedule::factory()->upcoming()->create();

        $this->actingAs($manager)->delete(route('tariff-schedule.destroy', $inForce))
            ->assertSessionHasErrorsIn('tariff_schedules', 'tariff_schedule');
        $this->actingAs($manager)->delete(route('tariff-schedule.destroy', $draft))->assertSessionHasNoErrors();
        $this->actingAs($manager)->delete(route('tariff-schedule.destroy', $upcoming))->assertSessionHasNoErrors();

        $this->assertModelExists($inForce);
        $this->assertModelMissing($draft);
        $this->assertModelMissing($upcoming);
    }

    public function test_tariffs_need_the_manage_tariffs_permission(): void
    {
        $secretary = tap(User::factory()->create())->assignRole(RoleEnum::Secretary->value);
        $schedule = TariffSchedule::factory()->draft()->create();

        $this->actingAs($secretary)->get(route('tariff-schedule.show', $schedule))->assertForbidden();
        $this->actingAs($secretary)->get(route('tariff-schedule.template'))->assertForbidden();
        $this->actingAs($secretary)->post(route('tariff-schedule.store'))->assertForbidden();
        $this->actingAs($secretary)->post(route('tariff-schedule.publish', $schedule))->assertForbidden();
        $this->actingAs($secretary)->put(route('tariff-adjustments.update', $schedule), ['adjustments' => []])->assertForbidden();
    }
}
