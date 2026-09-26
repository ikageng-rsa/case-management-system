<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\Court\CourtTier;
use App\Models\Court;
use App\Models\Matter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourtTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_casts_the_tier(): void
    {
        $court = Court::factory()->tier(CourtTier::Regional)->create();

        $this->assertSame(CourtTier::Regional, $court->fresh()->tier);
    }

    public function test_it_cites_the_court_by_name_and_seat(): void
    {
        $court = Court::factory()->create(['name' => 'High Court', 'seat' => 'Johannesburg']);

        $this->assertSame('High Court, Johannesburg', $court->label);
    }

    public function test_it_scopes_courts_by_tier(): void
    {
        Court::factory()->tier(CourtTier::High)->create();
        Court::factory()->tier(CourtTier::District)->create();

        $this->assertCount(1, Court::ofTier(CourtTier::High)->get());
    }

    public function test_it_has_matters(): void
    {
        $court = Court::factory()->create();
        Matter::factory()->for($court)->create();

        $this->assertCount(1, $court->matters);
    }
}
