<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Auth\Role as RoleEnum;
use App\Models\Billing\Bill;
use App\Models\Billing\Payment;
use App\Models\Client\Client;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientBillingTest extends TestCase
{
    use RefreshDatabase;

    protected Client $client;

    protected Matter $first;

    protected Matter $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);

        $this->client = Client::factory()->create();
        $this->first = Matter::factory()->for($this->client)->create();
        $this->second = Matter::factory()->for($this->client)->create();

        $owed = Bill::factory()->for($this->first)->issued('INV/2026/0001')->create(['total_cents' => 1000000, 'paid_cents' => 400000]);
        Payment::factory()->for($owed)->create(['amount_cents' => 400000, 'received_on' => '2026-11-10']);
        Bill::factory()->for($this->second)->issued('INV/2026/0002')->create(['total_cents' => 250000]);
        Bill::factory()->for($this->second)->create(['total_cents' => 900000]);

        // Another client's bill never shows.
        Bill::factory()->issued('INV/2026/0099')->create(['total_cents' => 7777700]);
    }

    public function test_someone_who_sees_clients_and_bills_sees_the_clients_bills_and_payments(): void
    {
        $director = tap(User::factory()->create())->assignRole(RoleEnum::Director->value);

        $this->actingAs($director)->get(route('client.show', $this->client))
            ->assertOk()
            ->assertSee('Billing')
            ->assertSee('R 8 500.00 owed on issued bills')
            ->assertSee('INV/2026/0001')
            ->assertSee('INV/2026/0002')
            ->assertSee('R 6 000.00 still owed')
            ->assertSee($this->second->reference)
            ->assertSee('R 4 000.00')
            ->assertSee('Receipt')
            ->assertDontSee('INV/2026/0099');
    }

    public function test_someone_who_sees_clients_but_not_bills_sees_no_billing_on_the_client(): void
    {
        $attorney = tap(User::factory()->create())->assignRole(RoleEnum::Attorney->value);
        MatterAssignment::factory()->for($this->first)->for($attorney)->create();

        $this->actingAs($attorney)->get(route('client.show', $this->client))
            ->assertOk()
            ->assertDontSee('owed on issued bills')
            ->assertDontSee('INV/2026/0001')
            ->assertDontSee('R 10 000.00');
    }

    public function test_bills_outside_a_persons_billing_reach_are_left_out(): void
    {
        $biller = tap(User::factory()->create())->givePermissionTo(['view_clients', 'view_bills']);
        MatterAssignment::factory()->for($this->first)->for($biller)->create();

        $this->actingAs($biller)->get(route('client.show', $this->client))
            ->assertOk()
            ->assertSee('R 6 000.00 owed on issued bills')
            ->assertSee('INV/2026/0001')
            ->assertDontSee('INV/2026/0002');
    }

    public function test_finance_has_no_client_pages(): void
    {
        $finance = tap(User::factory()->create())->assignRole(RoleEnum::Finance->value);

        $this->actingAs($finance)->get(route('client.show', $this->client))->assertForbidden();
    }
}
