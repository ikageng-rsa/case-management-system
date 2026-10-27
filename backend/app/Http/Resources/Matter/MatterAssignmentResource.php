<?php

declare(strict_types=1);

namespace App\Http\Resources\Matter;

use App\Http\Resources\ApiResource;
use App\Http\Resources\User\UserResource;
use App\Models\Matter\MatterAssignment;
use Illuminate\Http\Request;

/**
 * Someone on a matter's team, and in what capacity.
 *
 * @mixin MatterAssignment
 */
class MatterAssignmentResource extends ApiResource
{
    public function toType(Request $request): string
    {
        return 'matter-assignments';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'capacity' => $this->capacity->value,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
        ];
    }

    /** @return array<string, class-string> */
    public function toRelationships(Request $request): array
    {
        return [
            'user' => UserResource::class,
        ];
    }
}
