<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Billing\TariffScale;
use App\Enums\Narration\ActivityMeasure;
use App\Models\Billing\BillLine;
use App\Models\Billing\Disbursement;
use App\Models\Billing\FeeAgreement;
use App\Models\Billing\TariffItem;
use App\Models\Billing\TariffSchedule;
use App\Models\Matter\Matter;
use App\Models\Narration\Narration;
use App\Models\Reference\ActivityType;
use App\Models\User\User;
use App\Queries\Billing\WorkToBill;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnbilledWorkReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $finance;

    protected ActivityType $drafting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);

        $this->finance = tap(User::factory()->create())->assignRole(RoleEnum::Finance->value);
        $this->drafting = ActivityType::factory()->measuredIn(ActivityMeasure::Page)->create(['code' => 'DRAFT', 'name' => 'Drafting']);

        $tariff = TariffSchedule::factory()->startingOn('2026-01-01')->create();
        TariffItem::factory()->for($tariff, 'schedule')->create(['activity_type_id' => $this->drafting->id, 'scale_a_cents' => 4000]);
    }

    protected function work(Matter $matter, float $pages, string $on, ?ActivityType $type = null): Narration
    {
        return Narration::factory()->for($matter)->for($type ?? $this->drafting)->create(['quantity' => $pages, 'occurred_at' => $on]);
    }

    public function test_it_lists_matters_with_work_or_costs_not_yet_on_a_bill_oldest_first(): void
    {
        $mandate = Matter::factory()->create(['tariff_scale' => TariffScale::A]);
        FeeAgreement::factory()->for($mandate)->create();
        $this->work($mandate, 4, '2026-06-10');
        $billed = $this->work($mandate, 10, '2026-06-01');
        BillLine::factory()->create(['narration_id' => $billed->id, 'claim' => "statement:narration:{$billed->id}"]);
        Disbursement::factory()->for($mandate)->create(['incurred_on' => '2026-06-05', 'amount_cents' => 45000]);

        // No agreement and no scale: the work shows, unpriced, so someone puts it right.
        $bare = Matter::factory()->create(['tariff_scale' => null]);
        $this->work($bare, 2, '2026-05-01');

        // Under an estate agreement the work is covered by the share.
        $estate = Matter::factory()->create(['tariff_scale' => TariffScale::A]);
        FeeAgreement::factory()->for($estate)->estate()->create();
        $this->work($estate, 5, '2026-04-01');

        // Fully billed, and work that is never billed, are left out.
        $done = Matter::factory()->create(['tariff_scale' => TariffScale::A]);
        $line = $this->work($done, 3, '2026-03-01');
        BillLine::factory()->create(['narration_id' => $line->id, 'claim' => "statement:narration:{$line->id}"]);
        $this->work(Matter::factory()->create(), 1, '2026-02-01', ActivityType::factory()->nonBillable()->create());

        $report = app(WorkToBill::class)->for($this->finance);

        $this->assertSame([$bare->id, $mandate->id], $report['rows']->pluck('matter.id')->all());
        $this->assertSame(2, $report['rows']->total());
        $this->assertSame(1, $report['unpricedCount']);

        $row = $report['rows'][1];
        $this->assertSame(2, $row['entries']);
        $this->assertSame('2026-06-05', $row['since']->toDateString());
        $this->assertSame(16000, $row['workCents']);
        $this->assertSame(45000, $row['disbursementCents']);
        $this->assertSame(61000, $report['valueCents']);

        $this->actingAs($this->finance)->get(route('reports', ['tab' => 'unbilled']))
            ->assertOk()
            ->assertSee('Unbilled work')
            ->assertSee($mandate->reference)
            ->assertSee('R 160.00')
            ->assertSee('R 450.00')
            ->assertSee('No fee agreement')
            ->assertSee('1 not priced')
            ->assertSee(route('billing.matter', $mandate))
            ->assertDontSee($estate->reference)
            ->assertDontSee($done->reference);
    }

    public function test_it_shows_eight_matters_a_page_with_totals_for_all_of_them(): void
    {
        foreach (range(1, 10) as $day) {
            $matter = Matter::factory()->create(['tariff_scale' => TariffScale::A]);
            $this->work($matter, 1, sprintf('2026-06-%02d', $day));
        }

        $first = app(WorkToBill::class)->for($this->finance);
        $second = app(WorkToBill::class)->for($this->finance, 2);

        $this->assertCount(8, $first['rows']);
        $this->assertCount(2, $second['rows']);
        $this->assertSame(10, $second['rows']->total());
        $this->assertSame(40000, $second['valueCents']);
        $this->assertSame('2026-06-09', $second['rows'][0]['since']->toDateString());

        $this->actingAs($this->finance)->get(route('reports', ['tab' => 'unbilled']))
            ->assertOk()
            ->assertSee('tab=unbilled&amp;page=2', false);

        $this->actingAs($this->finance)->get(route('reports', ['tab' => 'unbilled', 'page' => 2]))
            ->assertOk()
            ->assertSee('R 400.00');
    }

    public function test_it_says_so_when_everything_is_billed_and_is_closed_to_those_without_the_permission(): void
    {
        $this->actingAs($this->finance)->get(route('reports', ['tab' => 'unbilled']))
            ->assertOk()
            ->assertSee('Everything recorded has been billed.');

        $attorney = tap(User::factory()->create())->assignRole(RoleEnum::Attorney->value);
        $this->actingAs($attorney)->get(route('reports', ['tab' => 'unbilled']))->assertForbidden();
    }
}
