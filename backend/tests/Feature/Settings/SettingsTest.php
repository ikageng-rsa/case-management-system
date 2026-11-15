<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Billing\BillKind;
use App\Enums\Billing\InvoiceTheme;
use App\Models\Billing\Bill;
use App\Models\Billing\BillLine;
use App\Models\User\User;
use App\Settings\FirmSettings;
use App\Settings\InvoiceSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        $this->actingAs($director)->get(route('settings'))->assertViewHas('tabs', ['billing' => 'Billing', 'invoices' => 'Invoices', 'firm' => 'Firm']);

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

    public function test_the_invoice_theme_and_payment_details_are_set_and_audited(): void
    {
        $user = $this->userWith('manage_billing_settings');

        $this->actingAs($user)->get(route('settings', ['tab' => 'invoices']))
            ->assertOk()
            ->assertSee('Classic')->assertSee('Modern')->assertSee('Minimal')
            ->assertSee('No account given, so statements do not say where to pay.');

        $this->actingAs($user)->put(route('settings.invoices.update'), [
            'theme' => 'modern',
            'accent_colour' => '#1f4e79',
            'payment_terms' => 'Payable within 14 days.',
            'bank_name' => 'Example Bank',
            'account_number' => '62 000 000 000',
            'branch_code' => '250655',
            'account_type' => 'Business cheque',
        ])->assertRedirect(route('settings', ['tab' => 'invoices']));

        $invoices = app(InvoiceSettings::class)->refresh();
        $this->assertSame(InvoiceTheme::Modern, $invoices->theme);
        $this->assertSame('#1F4E79', $invoices->accentColour);
        $this->assertTrue($invoices->hasBankAccount());
        $this->assertSame('Thandiwe Mokoena changed the invoice settings', Activity::query()->where('log_name', 'settings')->sole()->description);

        $this->actingAs($user)->get(route('settings', ['tab' => 'invoices']))->assertSee('Example Bank · Business cheque 62 000 000 000');
    }

    public function test_an_account_needs_its_bank_and_number_and_a_colour_must_be_one(): void
    {
        $this->actingAs($this->userWith('manage_billing_settings'))
            ->put(route('settings.invoices.update'), ['theme' => 'gaudy', 'accent_colour' => 'blue', 'bank_name' => 'Example Bank', 'account_number' => 'abc'])
            ->assertSessionHasErrorsIn('invoice_settings', ['theme', 'accent_colour', 'account_number']);
    }

    public function test_a_statement_says_how_to_pay_quoting_its_number_and_a_bill_of_cost_does_not(): void
    {
        $invoices = app(InvoiceSettings::class);
        $invoices->fill(['theme' => InvoiceTheme::Modern, 'accentColour' => '#1F4E79', 'bankName' => 'Example Bank', 'accountNumber' => '62000000000'])->save();

        $statement = BillLine::factory()->for(Bill::factory()->issued('INV/2026/0007'))->create()->bill->load(['matter', 'lines']);
        $html = view('pdf.bill', ['bill' => $statement])->render();
        $this->assertStringContainsString('Reference: <strong>INV/2026/0007</strong>', $html);
        $this->assertStringContainsString('Example Bank', $html);
        $this->assertStringContainsString('background: #1F4E79', $html);
        $this->assertStringContainsString('<div class="band">', $html);

        $billOfCost = BillLine::factory()->for(Bill::factory()->issued('INV/2026/0008')->state(['kind' => BillKind::BillOfCost]))->create()->bill->load(['matter', 'lines']);
        $this->assertStringNotContainsString('Reference:', view('pdf.bill', ['bill' => $billOfCost])->render());
    }

    public function test_the_firms_name_and_line_under_it_reach_every_page_title_the_sidebar_and_sign_in(): void
    {
        $user = $this->userWith('manage_firm_settings');

        $this->actingAs($user)->put(route('settings.firm.update'), ['name' => 'Example Attorneys', 'tagline' => 'Attorneys Inc.']);

        $this->actingAs($user)->get(route('settings', ['tab' => 'firm']))
            ->assertSee('<title>Settings — Example Attorneys</title>', false)
            ->assertSee('Attorneys Inc.');
        $this->actingAs($user)->get(route('dashboard'))->assertSee('<title>Dashboard — Example Attorneys</title>', false);

        auth()->logout();
        $this->get(route('login'))
            ->assertSee('<title>Sign in — Example Attorneys</title>', false)
            ->assertSee('Example Attorneys Attorneys Inc. · staff access only');
    }

    /** A one-pixel PNG, so the tests need no image library. */
    protected function png(string $name = 'letterhead.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC'));
    }

    public function test_a_letterhead_is_uploaded_printed_at_the_top_of_bills_and_can_be_replaced_or_removed(): void
    {
        Storage::fake(config('media-library.disk_name'));
        $user = $this->userWith('manage_firm_settings');
        $details = ['name' => 'Example Attorneys', 'website' => 'www.example.test', 'registration_number' => '2020/000000/21'];

        $this->actingAs($user)->put(route('settings.firm.update'), $details + ['letterhead' => $this->png()])
            ->assertSessionHasNoErrors();

        $firm = app(FirmSettings::class)->refresh();
        $first = $firm->letterheadPath;
        Storage::disk(config('media-library.disk_name'))->assertExists($first);
        $this->assertSame('www.example.test', $firm->website);

        $this->actingAs($user)->get(route('settings', ['tab' => 'firm']))->assertSee('data:image/png;base64,', false);
        $bill = BillLine::factory()->create()->bill->load(['matter', 'lines']);
        $html = view('pdf.bill', ['bill' => $bill])->render();
        $this->assertStringContainsString('<img src="data:image/png;base64,', $html);
        $this->assertStringNotContainsString('<div class="firm">', $html, 'the letterhead takes the place of the text');

        $this->actingAs($user)->put(route('settings.firm.update'), $details + ['letterhead' => $this->png('second.png')]);
        Storage::disk(config('media-library.disk_name'))->assertMissing($first);

        $this->actingAs($user)->put(route('settings.firm.update'), $details + ['remove_letterhead' => '1']);
        $this->assertNull(app(FirmSettings::class)->refresh()->letterheadPath);

        $html = view('pdf.bill', ['bill' => $bill])->render();
        $this->assertStringContainsString('<div class="firm">Example Attorneys</div>', $html);
        $this->assertStringContainsString('Company reg. 2020/000000/21', $html);
    }

    public function test_a_letterhead_must_be_a_png_or_jpg(): void
    {
        $this->actingAs($this->userWith('manage_firm_settings'))
            ->put(route('settings.firm.update'), ['name' => 'X', 'letterhead' => UploadedFile::fake()->create('letterhead.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrorsIn('firm_settings', 'letterhead');
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
