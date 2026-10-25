<?php

declare(strict_types=1);

namespace App\Http\Resources\Reference;

use App\Http\Resources\ApiResource;
use App\Models\Reference\ActivityType;
use Illuminate\Http\Request;

/** @mixin ActivityType */
class ActivityTypeResource extends ApiResource
{
    public function toType(Request $request): string
    {
        return 'activity-types';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'measure' => $this->measure->value,
            'increment' => $this->increment,
            'requires_court' => $this->requires_court,
        ];
    }
}
