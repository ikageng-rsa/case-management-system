<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Billing\FirmAccount;
use App\Models\Billing\Bill;
use App\Models\Billing\Payment;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentsPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $finance;

    protected Bill $bill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);

        $this->finance = tap(User::factory()->create())->assignRole(RoleEnum::Finance->value);
        $this->bill = Bill::factory()->issued('INV/2026/0007')->create(['addressee' => 'Mrs A Client', 'total_cents' => 2000000]);
    }

    protected function payment(int $cents, string $on, FirmAccount $account = FirmAccount::Business, ?Bill $bill = null): Payment
    {
        return Payment::factory()->for($bill ?? $this->bill)->create(['amount_cents' => $cents, 'received_on' => $on, 'account' => $account]);
    }

    public function test_finance_sees_every_payment_newest_first_with_the_total_received(): void
    {
        $this->payment(400000, '2026-11-10', FirmAccount::Trust);
        $this->payment(250000, '2026-11-14');
        Payment::factory()->for($this->bill)->reversed()->create(['amount_cents' => 999900, 'received_on' => '2026-11-12']);

        $this->actingAs($this->finance)->get(route('payments'))
            ->assertOk()
            ->assertSee('R 6 500.00 received')
            ->assertSee('2 payments.')
            ->assertSeeInOrder(['14 Nov 2026', '12 Nov 2026', '10 Nov 2026'])
            ->assertSee('INV/2026/0007')
            ->assertSee('Mrs A Client')
            ->assertSee('EFT into the trust account')
            ->assertSee('R 9 999.00')
            ->assertSee('Reversed')
            ->assertSee('Receipt');
    }

    public function test_payments_are_narrowed_by_date_and_by_account(): void
    {
        $this->payment(400000, '2026-11-10', FirmAccount::Trust);
        $this->payment(250000, '2026-11-14');
        $this->payment(100000, '2026-10-01');

        $this->actingAs($this->finance)->get(route('payments', ['from' => '2026-11-01', 'to' => '2026-11-30']))
            ->assertOk()
            ->assertSee('R 6 500.00 received')
            ->assertSee('2 payments matching')
            ->assertDontSee('1 Oct 2026');

        $this->actingAs($this->finance)->get(route('payments', ['account' => 'trust']))
            ->assertOk()
            ->assertSee('R 4 000.00 received')
            ->assertDontSee('14 Nov 2026');

        $this->actingAs($this->finance)->get(route('payments', ['from' => 'not-a-date', 'account' => 'savings']))
            ->assertOk()
            ->assertSee('R 7 500.00 received');

        $this->actingAs($this->finance)->get(route('payments', ['from' => '2027-01-01']))
            ->assertOk()
            ->assertSee('No payments match.');
    }

    public function test_the_page_needs_the_permission_and_covers_only_bills_in_reach(): void
    {
        $candidate = tap(User::factory()->create())->assignRole(RoleEnum::CandidateAttorney->value);
        $this->actingAs($candidate)->get(route('payments'))->assertForbidden();
        $this->actingAs($candidate)->get(route('dashboard'))->assertDontSee(route('payments'));
        $this->actingAs($this->finance)->get(route('dashboard'))->assertSee(route('payments'));

        // Someone given the permission without all billing sees payments on their own matters only.
        $mine = Matter::factory()->create();
        $viewer = tap(User::factory()->create())->givePermissionTo('view_payments');
        MatterAssignment::factory()->for($mine)->for($viewer)->create();
        $this->payment(300000, '2026-11-10', bill: Bill::factory()->for($mine)->issued('INV/2026/0009')->create(['total_cents' => 900000]));
        $this->payment(400000, '2026-11-11');

        $this->actingAs($viewer)->get(route('payments'))
            ->assertOk()
            ->assertSee('R 3 000.00 received')
            ->assertSee('INV/2026/0009')
            ->assertDontSee('INV/2026/0007')
            ->assertDontSee('Receipt');
    }
}
