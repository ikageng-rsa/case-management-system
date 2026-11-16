<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Enums\Auth\Permission;
use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Billing\BillKind;
use App\Models\Billing\Bill;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\User\User;
use App\Queries\Billing\Debtors;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtorsReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $director;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
        $this->travelTo('2026-11-16 10:00');

        $this->director = tap(User::factory()->create())->assignRole(RoleEnum::Director->value);
    }

    /** @param  array<string, mixed>  $state */
    protected function issued(string $number, string $on, int $totalCents, int $paidCents = 0, array $state = []): Bill
    {
        return Bill::factory()->issued($number)->create([
            'issued_at' => $on, 'total_cents' => $totalCents, 'paid_cents' => $paidCents, 'addressee' => "Addressee {$number}", ...$state,
        ]);
    }

    public function test_it_lists_what_is_owed_on_issued_bills_aged_by_days_since_issue(): void
    {
        $this->issued('INV/2026/0001', '2026-11-06', 1000000, 400000);
        $this->issued('INV/2026/0002', '2026-10-01', 250000);
        $this->issued('INV/2026/0003', '2026-07-01', 300000);
        $this->issued('INV/2026/0004', '2026-09-01', 500000, state: ['kind' => BillKind::BillOfCost]);
        // Nothing is owed on a settled bill, a draft or a voided bill.
        $this->issued('INV/2026/0005', '2026-11-01', 100000, 100000);
        Bill::factory()->create(['total_cents' => 900000, 'addressee' => 'A draft']);
        Bill::factory()->void()->create(['total_cents' => 700000, 'number' => 'INV/2026/0006', 'issued_at' => '2026-10-01']);

        $report = app(Debtors::class)->for($this->director);

        $clients = $report['owedByClients'];
        $this->assertSame(['INV/2026/0003', 'INV/2026/0002', 'INV/2026/0001'], $clients['rows']->pluck('bill.number')->all());
        $this->assertSame([138, 46, 10], $clients['rows']->pluck('days')->all());
        $this->assertSame(1150000, $clients['totalCents']);
        $this->assertSame(['0–30 days' => 600000, '31–60 days' => 250000, '61–90 days' => 0, 'Over 90 days' => 300000], $clients['bucketCents']);

        $otherSide = $report['owedOnBillsOfCost'];
        $this->assertSame(['INV/2026/0004'], $otherSide['rows']->pluck('bill.number')->all());
        $this->assertSame(500000, $otherSide['bucketCents']['61–90 days']);

        $this->actingAs($this->director)->get(route('reports'))
            ->assertOk()
            ->assertSee('Owed by clients')
            ->assertSee('R 11 500.00')
            ->assertSee('INV/2026/0001')
            ->assertSee('Addressee INV/2026/0001')
            ->assertSee('10 days ago')
            ->assertSee('of R 10 000.00')
            ->assertSee('Owed on bills of cost')
            ->assertSee('INV/2026/0004')
            ->assertDontSee('INV/2026/0005')
            ->assertDontSee('INV/2026/0006')
            ->assertDontSee('A draft');
    }

    public function test_a_bill_opened_from_the_report_leads_back_to_the_report(): void
    {
        $bill = $this->issued('INV/2026/0001', '2026-11-06', 1000000);

        $this->actingAs($this->director)->get(route('reports'))
            ->assertSee(route('bill.show', [$bill, 'from' => 'debtors']));

        $this->actingAs($this->director)->get(route('bill.show', [$bill, 'from' => 'debtors']))
            ->assertOk()
            ->assertSee('← Debtors report');

        $this->actingAs($this->director)->get(route('bill.show', $bill))
            ->assertOk()
            ->assertDontSee('Debtors report')
            ->assertSee("← {$bill->matter->reference}");
    }

    public function test_an_empty_report_says_nothing_is_owed(): void
    {
        $this->actingAs($this->director)->get(route('reports', ['tab' => 'debtors']))
            ->assertOk()
            ->assertSee('No client owes anything on an issued bill.')
            ->assertSee('Nothing is owed on a bill of cost.');
    }

    public function test_it_covers_only_the_matters_a_person_may_see(): void
    {
        $mine = Matter::factory()->create();
        $attorney = tap(User::factory()->create())->assignRole(RoleEnum::Attorney->value);
        $attorney->givePermissionTo(Permission::ViewBillingReports->value);
        MatterAssignment::factory()->for($mine)->for($attorney)->create();

        $this->issued('INV/2026/0001', '2026-11-01', 100000, state: ['matter_id' => $mine->id]);
        $this->issued('INV/2026/0002', '2026-11-01', 200000);

        $this->assertFalse($attorney->can(Permission::ViewAllMatters->value));

        $this->actingAs($attorney)->get(route('reports'))
            ->assertOk()
            ->assertSee('INV/2026/0001')
            ->assertDontSee('INV/2026/0002');
    }

    public function test_the_reports_page_and_its_link_need_the_permission(): void
    {
        $secretary = tap(User::factory()->create())->assignRole(RoleEnum::Secretary->value);

        $this->actingAs($secretary)->get(route('reports'))->assertForbidden();
        $this->actingAs($secretary)->get(route('dashboard'))->assertOk()->assertDontSee(route('reports'));
        $this->actingAs($this->director)->get(route('dashboard'))->assertOk()->assertSee(route('reports'));
    }
}
