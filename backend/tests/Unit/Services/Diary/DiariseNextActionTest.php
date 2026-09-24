<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Diary;

use App\Models\Matter;
use App\Models\User;
use App\Services\Diary\DiariseNextAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiariseNextActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_diarises_an_action_against_a_matter_and_assignee(): void
    {
        $matter = Matter::factory()->create();
        $assignee = User::factory()->create();

        $entry = (new DiariseNextAction)->diarise($matter, $assignee, 'Serve the court order.', now()->addWeek());

        $this->assertTrue($entry->exists);
        $this->assertTrue($entry->matter->is($matter));
        $this->assertTrue($entry->assignee->is($assignee));
        $this->assertTrue($entry->isPending());
    }

    public function test_a_future_entry_is_not_overdue(): void
    {
        $entry = (new DiariseNextAction)->diarise(
            Matter::factory()->create(),
            User::factory()->create(),
            'Follow up with client.',
            now()->addDays(3),
        );

        $this->assertFalse($entry->isOverdue());
    }
}
