<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Reference;

use App\Enums\Document\DocumentKind;
use App\Enums\Matter\Assignment;
use App\Models\Reference\ActivityType;
use App\Models\User\User;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\ApiTestCase;

class LookupTest extends ApiTestCase
{
    public function test_the_lists_need_a_token(): void
    {
        foreach (['activity-types', 'assignment-capacities', 'document-kinds'] as $list) {
            $this->getJson("/api/v1/{$list}")->assertJsonApiError(401);
        }
    }

    public function test_activity_types_are_listed_without_the_retired_ones(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $consultation = ActivityType::factory()->create(['code' => 'CONS', 'name' => 'Consultation', 'requires_court' => false]);
        ActivityType::factory()->create(['code' => 'OLD', 'name' => 'Retired'])->delete();

        $this->getJson('/api/v1/activity-types')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'activity-types')
            ->assertJsonPath('data.0.id', (string) $consultation->id)
            ->assertJsonPath('data.0.attributes.code', 'CONS')
            ->assertJsonPath('data.0.attributes.measure', $consultation->measure->value)
            ->assertJsonPath('data.0.attributes.requires_court', false);
    }

    public function test_assignment_capacities_say_which_are_held_by_one_person(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/assignment-capacities')
            ->assertOk()
            ->assertJsonCount(count(Assignment::cases()), 'data')
            ->assertJsonPath('data.0.type', 'assignment-capacities')
            ->assertJsonPath('data.0.id', Assignment::Responsible->value)
            ->assertJsonPath('data.0.attributes.name', 'Responsible')
            ->assertJsonPath('data.0.attributes.exclusive', true)
            ->assertJsonPath('data.2.attributes.exclusive', false);
    }

    public function test_document_kinds_are_listed(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/document-kinds')
            ->assertOk()
            ->assertJsonCount(count(DocumentKind::cases()), 'data')
            ->assertJsonPath('data.3.id', DocumentKind::CourtOrder->value)
            ->assertJsonPath('data.3.attributes.name', 'Court Order');
    }
}
