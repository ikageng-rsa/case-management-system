<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\Narrations;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Narration\ActivityMeasure;
use App\Models\Billing\BillLine;
use App\Models\Narration\Narration;
use App\Models\Reference\ActivityType;
use App\Models\User\User;
use App\Queries\Narrations\UnbilledTime;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnbilledTimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_it_adds_up_billable_minutes_as_hours(): void
    {
        Narration::factory()->create(['quantity' => 30]);
        Narration::factory()->create(['quantity' => 60]);

        $time = (new UnbilledTime)->for($this->userWith(RoleEnum::Director));

        $this->assertSame(1.5, $time['unbilledHours']);
        $this->assertSame(2, $time['unbilledEntriesCount']);
    }

    public function test_it_leaves_out_work_that_is_not_billed_or_not_timed(): void
    {
        Narration::factory()->create(['quantity' => 30]);
        Narration::factory()->for(ActivityType::factory()->nonBillable())->create(['quantity' => 60]);
        Narration::factory()->for(ActivityType::factory()->measuredIn(ActivityMeasure::Page))->create(['quantity' => 12]);

        $time = (new UnbilledTime)->for($this->userWith(RoleEnum::Director));

        $this->assertSame(0.5, $time['unbilledHours']);
        $this->assertSame(1, $time['unbilledEntriesCount']);
    }

    public function test_someone_who_does_not_see_bills_sees_only_their_own_time(): void
    {
        $attorney = $this->userWith(RoleEnum::Attorney);
        Narration::factory()->for($attorney, 'author')->create(['quantity' => 45]);
        Narration::factory()->create(['quantity' => 60]);

        $time = (new UnbilledTime)->for($attorney);

        $this->assertSame(0.75, $time['unbilledHours']);
        $this->assertSame(1, $time['unbilledEntriesCount']);

        // A secretary browses every matter, but the firm's hours are for those who see bills.
        $secretary = $this->userWith(RoleEnum::Secretary);
        Narration::factory()->for($secretary, 'author')->create(['quantity' => 30]);

        $this->assertSame(0.5, (new UnbilledTime)->for($secretary)['unbilledHours']);
        $this->assertSame(2.25, (new UnbilledTime)->for($this->userWith(RoleEnum::Finance))['unbilledHours']);
    }

    public function test_time_on_a_live_bill_is_no_longer_unbilled_until_the_bill_is_voided(): void
    {
        $director = $this->userWith(RoleEnum::Director);
        $billed = Narration::factory()->create(['quantity' => 60]);
        Narration::factory()->create(['quantity' => 30]);

        $line = BillLine::factory()->create(['narration_id' => $billed->id, 'claim' => "statement:narration:{$billed->id}"]);

        $this->assertSame(0.5, (new UnbilledTime)->for($director)['unbilledHours']);

        // Voiding gives the claim up, so the time is unbilled again.
        $line->update(['claim' => null]);

        $this->assertSame(1.5, (new UnbilledTime)->for($director)['unbilledHours']);
    }

    protected function userWith(RoleEnum $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }
}
