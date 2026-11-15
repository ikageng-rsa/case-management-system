<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Billing\BillingTiming;
use App\Enums\Billing\DrawingFeeBase;
use App\Models\User\User;
use App\Settings\BillingSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class BillingSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    protected function userWithRole(RoleEnum $role): User
    {
        return tap(User::factory()->create(['name' => 'Thandi Director']))->assignRole($role->value);
    }

    public function test_the_firm_starts_on_the_terms_it_described(): void
    {
        $settings = app(BillingSettings::class);

        $this->assertSame(25.0, $settings->contingencyPercent);
        $this->assertSame(3.5, $settings->estatePercent);
        $this->assertSame(5.0, $settings->drawingFeePercent);
        $this->assertSame(DrawingFeeBase::Fees, $settings->drawingFeeBase);
        $this->assertSame(BillingTiming::Manual, $settings->billingTiming);
    }

    public function test_the_director_changes_the_settings_and_the_change_is_audited(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);

        $this->actingAs($director)->get(route('billing-settings'))->assertOk()->assertSee('25%')->assertSee('Contingency fee (%)');

        $this->actingAs($director)->put(route('billing-settings.update'), [
            'contingency_percent' => '20',
            'estate_percent' => '3.5',
            'drawing_fee_percent' => '5',
            'drawing_fee_base' => 'fees_and_disbursements',
            'billing_timing' => 'monthly',
        ])->assertRedirect(route('billing-settings'));

        $settings = app(BillingSettings::class)->refresh();
        $this->assertSame(20.0, $settings->contingencyPercent);
        $this->assertSame(DrawingFeeBase::FeesAndDisbursements, $settings->drawingFeeBase);
        $this->assertSame(BillingTiming::Monthly, $settings->billingTiming);

        $activity = Activity::query()->where('log_name', 'billing')->sole();
        $this->assertSame('Thandi Director changed the billing settings', $activity->description);
        // Compared by key, not order: MySQL keeps JSON keys in an order of its own.
        $this->assertEquals(['contingencyPercent' => '25', 'drawingFeeBase' => 'fees', 'billingTiming' => 'manual'], $activity->properties['old']);
    }

    public function test_a_percentage_cannot_go_above_a_hundred(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Director))->put(route('billing-settings.update'), [
            'contingency_percent' => '125',
            'estate_percent' => '3.5',
            'drawing_fee_percent' => '5',
            'drawing_fee_base' => 'fees',
            'billing_timing' => 'manual',
        ])->assertSessionHasErrorsIn('billing_settings', 'contingency_percent');

        $this->assertSame(25.0, app(BillingSettings::class)->refresh()->contingencyPercent);
    }

    public function test_billing_settings_need_their_own_permission(): void
    {
        $manager = $this->userWithRole(RoleEnum::Manager);

        $this->actingAs($manager)->get(route('billing-settings'))->assertForbidden();
        $this->actingAs($manager)->put(route('billing-settings.update'), [])->assertForbidden();
    }
}
