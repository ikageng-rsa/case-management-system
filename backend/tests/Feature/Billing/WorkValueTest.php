<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Billing\TariffScale;
use App\Enums\Narration\ActivityMeasure;
use App\Models\Billing\TariffItem;
use App\Models\Billing\TariffSchedule;
use App\Models\Matter\Matter;
use App\Models\Narration\Narration;
use App\Models\Reference\ActivityType;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkValueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    public function test_the_billing_tab_values_the_work_on_the_tariff_and_lists_what_could_not_be_priced(): void
    {
        $director = tap(User::factory()->create())->assignRole(RoleEnum::Director->value);
        $attorney = tap(User::factory()->create())->assignRole(RoleEnum::Attorney->value);
        $matter = Matter::factory()->create(['tariff_scale' => TariffScale::A]);

        $drafting = ActivityType::factory()->measuredIn(ActivityMeasure::Page)->create(['name' => 'Drafting']);
        $perusal = ActivityType::factory()->measuredIn(ActivityMeasure::Page)->create(['name' => 'Perusal']);
        $tariff = TariffSchedule::factory()->startingOn('2026-01-01')->create(['name' => 'Tariff 2026']);
        TariffItem::factory()->for($tariff, 'schedule')->create(['activity_type_id' => $drafting->getKey(), 'scale_a_cents' => 4000]);

        Narration::factory()->for($matter)->for($drafting)->for($attorney, 'author')->create(['quantity' => 3, 'occurred_at' => '2026-06-01']);
        Narration::factory()->for($matter)->for($perusal)->for($attorney, 'author')->create(['quantity' => 10, 'occurred_at' => '2026-06-01']);

        $this->actingAs($director)->get(route('matter.show', [$matter, 'tab' => 'billing']))
            ->assertOk()
            ->assertSee('R 120.00')
            ->assertSee('1 billable narration on the tariff, scale A')
            ->assertSee('Tariff 2026 has no scale A rate for Perusal.');
    }
}
