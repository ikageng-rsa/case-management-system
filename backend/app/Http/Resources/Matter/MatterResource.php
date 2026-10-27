<?php

declare(strict_types=1);

namespace App\Http\Resources\Matter;

use App\Http\Resources\ApiResource;
use App\Http\Resources\Client\ClientResource;
use App\Http\Resources\Reference\CourtResource;
use App\Http\Resources\Reference\MatterTypeResource;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use Illuminate\Http\Request;

/**
 * A matter as the app shows it. In a list it comes with its client and type; on
 * its own it also brings its court and the team carrying it, and what the user
 * may do on it.
 *
 * @mixin Matter
 */
class MatterResource extends ApiResource
{
    private bool $showsTeam = false;

    public function toType(Request $request): string
    {
        return 'matters';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'title' => $this->title,
            'status' => $this->isOpen() ? 'open' : 'closed',
            'instructed_at' => $this->instructed_at?->toIso8601String(),
            'prescribes_at' => $this->prescribes_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'next_due_at' => $this->whenLoaded('diaryEntries', fn () => $this->diaryEntries->min('due_at')?->toIso8601String()),
            'my_capacities' => $this->whenLoaded('assignments', fn () => $this->assignments
                ->filter(fn (MatterAssignment $assignment) => $assignment->isActive() && $assignment->user_id === $request->user()->getKey())
                ->map(fn (MatterAssignment $assignment) => $assignment->capacity->value)
                ->values()),
            'documents_count' => $this->whenCounted('media'),
        ];
    }

    /**
     * Only the team still on the matter is given, and only where it was loaded as
     * such: a list carries past assignments too, to work out the user's own.
     *
     * @return array<string, class-string>
     */
    public function toRelationships(Request $request): array
    {
        return [
            'client' => ClientResource::class,
            'matterType' => MatterTypeResource::class,
            'court' => CourtResource::class,
            ...($this->showsTeam ? ['assignments' => MatterAssignmentResource::class] : []),
        ];
    }

    /** @return array<string, mixed> */
    public function toMeta(Request $request): array
    {
        $user = $request->user();

        return [
            'can' => [
                'work' => (bool) $user?->can('work', $this->resource),
                'assign' => (bool) $user?->can('assign', $this->resource),
                'view_audit' => (bool) $user?->can('viewAudit', $this->resource),
            ],
        ];
    }

    /** Bring the matter's current team, which the controller has loaded as active assignments. */
    public function withTeam(): static
    {
        $this->showsTeam = true;

        return $this;
    }
}
