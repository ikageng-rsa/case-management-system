<?php

declare(strict_types=1);

namespace App\Http\Resources\Narration;

use App\Http\Resources\ApiResource;
use App\Http\Resources\Reference\ActivityTypeResource;
use App\Http\Resources\User\UserResource;
use App\Models\Narration\Narration;
use Illuminate\Http\Request;

/**
 * A line on a matter's ledger: what was done, how much of it, and by whom.
 *
 * @mixin Narration
 */
class NarrationResource extends ApiResource
{
    public function toType(Request $request): string
    {
        return 'narrations';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'body' => $this->body,
            'quantity' => (float) $this->quantity,
            'billable_units' => $this->billableUnits(),
            'occurred_at' => $this->occurred_at->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, class-string> */
    public function toRelationships(Request $request): array
    {
        return [
            'activityType' => ActivityTypeResource::class,
            'author' => UserResource::class,
        ];
    }
}
