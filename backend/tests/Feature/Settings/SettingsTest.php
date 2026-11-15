<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Enums\Auth\Role as RoleEnum;
use App\Models\Billing\BillLine;
use App\Models\User\User;
use App\Settings\FirmSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    protected function userWith(string ...$permissions): User
    {
        return tap(User::factory()->create(['name' => 'Thandiwe Mokoena']))->givePermissionTo($permissions);
    }

    public function test_a_person_sees_only_the_tabs_they_may_change(): void
    {
        $this->actingAs($this->userWith('manage_firm_settings'))->get(route('settings', ['tab' => 'billing']))
            ->assertOk()
            ->assertViewHas('tabs', ['firm' => 'Firm'])
            ->assertViewHas('tab', 'firm');

        $director = tap(User::factory()->create())->assignRole(RoleEnum::Director->value);
        $this->actingAs($director)->get(route('settings'))->assertViewHas('tabs', ['billing' => 'Billing', 'firm' => 'Firm']);

        $this->actingAs(tap(User::factory()->create())->assignRole(RoleEnum::Manager->value))->get(route('settings'))->assertForbidden();
    }

    public function test_the_old_billing_settings_address_still_leads_there(): void
    {
        $this->actingAs($this->userWith('manage_billing_settings'))->get('/billing-settings')->assertRedirect('/settings?tab=billing');
    }

    public function test_the_firm_details_are_changed_in_the_app_and_audited(): void
    {
        $user = $this->userWith('manage_firm_settings');

        $this->actingAs($user)->put(route('settings.firm.update'), [
            'name' => 'Example Attorneys',
            'address' => "12 Main Street\r\n\r\nRustenburg\r\n0299",
            'phone' => '014 000 0000',
            'email' => 'accounts@example.test',
        ])->assertRedirect(route('settings', ['tab' => 'firm']));

        $firm = app(FirmSettings::class)->refresh();
        $this->assertSame('Example Attorneys', $firm->name);
        $this->assertSame(['12 Main Street', 'Rustenburg', '0299'], $firm->addressLines);
        $this->assertSame('accounts@example.test', $firm->email);

        $this->assertSame('Thandiwe Mokoena changed the firm details', Activity::query()->where('log_name', 'settings')->sole()->description);

        // The app and the bills carry the new details.
        $this->actingAs($user)->get(route('settings', ['tab' => 'firm']))->assertSee('Example Attorneys')->assertSee('Rustenburg');
        $bill = BillLine::factory()->create()->bill->load(['matter', 'lines']);
        $this->assertStringContainsString('Example Attorneys', view('pdf.bill', ['bill' => $bill])->render());
        $this->assertStringContainsString('12 Main Street', view('pdf.bill', ['bill' => $bill])->render());
    }

    public function test_a_name_is_needed_and_an_email_must_be_one(): void
    {
        $this->actingAs($this->userWith('manage_firm_settings'))
            ->put(route('settings.firm.update'), ['name' => '', 'email' => 'not an email'])
            ->assertSessionHasErrorsIn('firm_settings', ['name', 'email']);
    }

    public function test_the_firm_details_need_their_own_permission(): void
    {
        $this->actingAs($this->userWith('manage_billing_settings'))
            ->put(route('settings.firm.update'), ['name' => 'X'])
            ->assertForbidden();
    }
}
