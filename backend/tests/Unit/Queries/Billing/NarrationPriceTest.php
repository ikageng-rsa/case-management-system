<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\Billing;

use App\Enums\Billing\TariffScale;
use App\Enums\Narration\ActivityMeasure;
use App\Models\Billing\TariffAdjustment;
use App\Models\Billing\TariffItem;
use App\Models\Billing\TariffSchedule;
use App\Models\Matter\Matter;
use App\Models\Narration\Narration;
use App\Models\Reference\ActivityType;
use App\Models\User\Role;
use App\Models\User\User;
use App\Queries\Billing\NarrationPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NarrationPriceTest extends TestCase
{
    use RefreshDatabase;

    protected ActivityType $drafting;

    protected TariffSchedule $tariff;

    protected Role $director;

    protected Role $attorney;

    protected function setUp(): void
    {
        parent::setUp();

        $this->drafting = ActivityType::factory()->measuredIn(ActivityMeasure::Page)->create(['code' => 'DRAFT', 'name' => 'Drafting']);
        $this->tariff = TariffSchedule::factory()->startingOn('2026-04-01')->create(['name' => 'Tariff 2026']);
        TariffItem::factory()->for($this->tariff, 'schedule')->create([
            'activity_type_id' => $this->drafting->getKey(), 'scale_a_cents' => 4000, 'scale_b_cents' => 5500,
        ]);

        $this->director = Role::findOrCreate('director');
        $this->attorney = Role::findOrCreate('attorney');
        TariffAdjustment::factory()->for($this->tariff, 'schedule')->percent(45.45)->create(['role_id' => $this->director->getKey()]);
    }

    protected function narration(array $roles, ?TariffScale $scale = TariffScale::B, string $on = '2026-06-01', float $pages = 4): Narration
    {
        $author = User::factory()->create();
        $author->assignRole($roles);

        return Narration::factory()
            ->for(Matter::factory()->create(['tariff_scale' => $scale]))
            ->for($this->drafting)
            ->for($author, 'author')
            ->create(['quantity' => $pages, 'occurred_at' => $on]);
    }

    public function test_it_is_the_units_at_the_authors_rate_on_the_matters_scale(): void
    {
        $price = (new NarrationPrice)->for($this->narration(['attorney']));

        $this->assertTrue($price['priced']);
        $this->assertSame(4, $price['units']);
        $this->assertSame(5500, $price['rateCents']);
        $this->assertSame(22000, $price['amountCents']);
        $this->assertTrue($price['role']->is($this->attorney));
        $this->assertTrue($price['schedule']->is($this->tariff));
    }

    public function test_someone_with_several_roles_charges_at_the_highest(): void
    {
        $price = (new NarrationPrice)->for($this->narration(['attorney', 'director']));

        // R 55.00 + 45.45% = R 79.9975, to the cent.
        $this->assertSame(8000, $price['rateCents']);
        $this->assertTrue($price['role']->is($this->director));
    }

    public function test_a_scale_given_for_the_bill_is_used_over_the_matters(): void
    {
        $price = (new NarrationPrice)->for($this->narration(['attorney']), TariffScale::A);

        $this->assertSame(16000, $price['amountCents']);
    }

    public function test_work_is_priced_on_the_tariff_in_force_when_it_was_done(): void
    {
        $newer = TariffSchedule::factory()->startingOn('2027-04-01')->create();
        TariffItem::factory()->for($newer, 'schedule')->create(['activity_type_id' => $this->drafting->getKey(), 'scale_b_cents' => 9900]);

        $this->assertSame(5500, (new NarrationPrice)->for($this->narration(['attorney'], on: '2027-03-31'))['rateCents']);
        $this->assertSame(9900, (new NarrationPrice)->for($this->narration(['attorney'], on: '2027-04-01'))['rateCents']);
    }

    public function test_a_draft_tariff_is_not_used(): void
    {
        $draft = TariffSchedule::factory()->startingOn('2026-05-01')->draft()->create();
        TariffItem::factory()->for($draft, 'schedule')->create(['activity_type_id' => $this->drafting->getKey(), 'scale_b_cents' => 1]);

        $this->assertSame(5500, (new NarrationPrice)->for($this->narration(['attorney']))['rateCents']);
    }

    public function test_time_on_top_is_left_for_the_bill_and_only_the_work_is_priced(): void
    {
        $travelTime = ActivityType::factory()->measuredIn(ActivityMeasure::Minutes, 15)->create(['name' => 'Travelling time']);
        $travel = ActivityType::factory()->measuredIn(ActivityMeasure::Kilometre)->create(['name' => 'Travelling', 'time_activity_type_id' => $travelTime->getKey()]);
        TariffItem::factory()->for($this->tariff, 'schedule')->create(['activity_type_id' => $travel->getKey(), 'scale_b_cents' => 750]);

        $narration = Narration::factory()
            ->for(Matter::factory()->create(['tariff_scale' => TariffScale::B]))
            ->for($travel)
            ->for(tap(User::factory()->create())->assignRole(['attorney']), 'author')
            ->create(['quantity' => 80, 'minutes' => 50, 'occurred_at' => '2026-06-01']);

        $price = (new NarrationPrice)->for($narration);

        // 80 km at R 7.50; the 50 minutes are rated when the bill is raised.
        $this->assertTrue($price['priced']);
        $this->assertSame(60000, $price['amountCents']);
    }

    public function test_work_that_cannot_be_priced_says_why(): void
    {
        $price = new NarrationPrice;

        $this->assertSame('The matter has no tariff scale.', $price->for($this->narration(['attorney'], scale: null))['reason']);
        $this->assertSame('No tariff was in force on 1 Jan 2026.', $price->for($this->narration(['attorney'], on: '2026-01-01'))['reason']);
        $this->assertSame('Tariff 2026 has no scale C rate for Drafting.', $price->for($this->narration(['attorney'], scale: TariffScale::C))['reason']);

        $admin = Narration::factory()->for(ActivityType::factory()->nonBillable())->create();
        $this->assertSame('This kind of work is not billed.', $price->for($admin)['reason']);
    }
}
