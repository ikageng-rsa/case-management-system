<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Narrations;

use App\Models\Matter\Matter;
use App\Models\Reference\ActivityType;
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

    public function test_it_requires_the_matter_to_have_a_court_for_a_court_bound_activity(): void
    {
        $type = ActivityType::factory()->appearance()->create();

        $this->expectException(InvalidArgumentException::class);

        (new RecordNarration)->record(
            Matter::factory()->create(['court_id' => null]),
            $type,
            User::factory()->create(),
            'Appeared for the applicant.',
            60,
        );
    }

    public function test_an_appearance_is_recorded_against_the_matters_own_court(): void
    {
        $type = ActivityType::factory()->appearance()->create();
        $matter = Matter::factory()->create();

        $narration = (new RecordNarration)->record($matter, $type, User::factory()->create(), 'Appeared for the applicant.', 60);

        $this->assertTrue($narration->matter->court->is($matter->court));
    }

    public function test_other_work_does_not_need_a_court(): void
    {
        $type = ActivityType::factory()->create(['requires_court' => false]);

        $narration = (new RecordNarration)->record(
            Matter::factory()->create(['court_id' => null]),
            $type,
            User::factory()->create(),
            'Reviewed correspondence.',
            15,
        );

        $this->assertNotNull($narration->getKey());
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
