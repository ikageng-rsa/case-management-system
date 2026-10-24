<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\Matters;

use App\Enums\Narration\ActivityMeasure;
use App\Models\Matter\Matter;
use App\Models\Narration;
use App\Models\Reference\ActivityType;
use App\Models\User;
use App\Queries\Matters\MatterLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatterLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_every_narration_on_the_matter_newest_first(): void
    {
        $matter = Matter::factory()->create();
        $older = Narration::factory()->for($matter)->occurredAt(now()->subDay())->create();
        $newer = Narration::factory()->for($matter)->occurredAt(now())->create();
        Narration::factory()->create();

        $ledger = (new MatterLedger)->for($matter, User::factory()->create(), null);

        $this->assertSame([$newer->id, $older->id], $ledger['narrations']->pluck('id')->all());
        $this->assertSame(2, $ledger['allCount']);
    }

    public function test_it_narrows_to_the_users_own_narrations(): void
    {
        $matter = Matter::factory()->create();
        $user = User::factory()->create();
        $mine = Narration::factory()->for($matter)->for($user, 'author')->create();
        Narration::factory()->for($matter)->create();

        $ledger = (new MatterLedger)->for($matter, $user, 'mine');

        $this->assertSame([$mine->id], $ledger['narrations']->pluck('id')->all());
        $this->assertSame(1, $ledger['mineCount']);
        $this->assertSame(2, $ledger['allCount']);
    }

    public function test_it_narrows_to_one_activity_code_and_counts_each_code(): void
    {
        $matter = Matter::factory()->create();
        $drafting = ActivityType::factory()->create(['code' => 'DRAF', 'name' => 'Drafting']);
        $consultation = ActivityType::factory()->create(['code' => 'CONS', 'name' => 'Consultation']);
        $drafted = Narration::factory()->for($matter)->for($drafting)->create();
        Narration::factory()->count(2)->for($matter)->for($consultation)->create();

        $ledger = (new MatterLedger)->for($matter, User::factory()->create(), 'DRAF');

        $this->assertSame([$drafted->id], $ledger['narrations']->pluck('id')->all());
        $this->assertSame(['label' => 'drafting', 'count' => 1], $ledger['filters']['DRAF']);
        $this->assertSame(['label' => 'consultation', 'count' => 2], $ledger['filters']['CONS']);
    }

    public function test_it_totals_only_timed_activity_towards_minutes_recorded(): void
    {
        $matter = Matter::factory()->create();
        Narration::factory()->for($matter)->create(['quantity' => 30]);
        Narration::factory()->for($matter)->create(['quantity' => 15]);
        Narration::factory()->for($matter)
            ->for(ActivityType::factory()->measuredIn(ActivityMeasure::Page))
            ->create(['quantity' => 12]);

        $ledger = (new MatterLedger)->for($matter, User::factory()->create(), null);

        $this->assertSame(45.0, $ledger['minutesRecorded']);
    }
}
