<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Narrations;

use App\Models\ActivityType;
use App\Models\Court;
use App\Models\Matter;
use App\Models\User;
use App\Services\Narrations\RecordNarration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RecordNarrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_a_narration_against_a_matter_and_author(): void
    {
        $matter = Matter::factory()->create();
        $author = User::factory()->create();
        $type = ActivityType::factory()->create();

        $narration = (new RecordNarration)->record($matter, $type, $author, 'Drafted the summons.', 45);

        $this->assertTrue($narration->exists);
        $this->assertTrue($narration->matter->is($matter));
        $this->assertTrue($narration->author->is($author));
        $this->assertSame('Drafted the summons.', $narration->body);
    }

    public function test_it_derives_billable_units_from_the_activity_type(): void
    {
        // Six-minute units: 45 minutes rounds up to 8 units.
        $type = ActivityType::factory()->create(['increment' => 6]);

        $narration = (new RecordNarration)->record(
            Matter::factory()->create(),
            $type,
            User::factory()->create(),
            'Telephone attendance.',
            45,
        );

        $this->assertSame(8, $narration->billableUnits());
    }

    public function test_it_requires_a_court_for_a_court_bound_activity(): void
    {
        $type = ActivityType::factory()->appearance()->create();

        $this->expectException(InvalidArgumentException::class);

        (new RecordNarration)->record(
            Matter::factory()->create(),
            $type,
            User::factory()->create(),
            'Appeared for the applicant.',
            60,
        );
    }

    public function test_it_records_the_court_for_an_appearance(): void
    {
        $type = ActivityType::factory()->appearance()->create();
        $court = Court::factory()->create();

        $narration = (new RecordNarration)->record(
            Matter::factory()->create(),
            $type,
            User::factory()->create(),
            'Appeared for the applicant.',
            60,
            $court,
        );

        $this->assertTrue($narration->court->is($court));
    }

    public function test_it_ignores_a_court_for_a_non_court_activity(): void
    {
        $type = ActivityType::factory()->create(['requires_court' => false]);

        $narration = (new RecordNarration)->record(
            Matter::factory()->create(),
            $type,
            User::factory()->create(),
            'Reviewed correspondence.',
            15,
            Court::factory()->create(),
        );

        $this->assertNull($narration->court_id);
    }

    public function test_it_defaults_the_occurrence_time_to_now(): void
    {
        $narration = (new RecordNarration)->record(
            Matter::factory()->create(),
            ActivityType::factory()->create(),
            User::factory()->create(),
            'Filed the notice of motion.',
            10,
        );

        $this->assertNotNull($narration->occurred_at);
    }
}
