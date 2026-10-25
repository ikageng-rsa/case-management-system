<?php

declare(strict_types=1);

namespace App\Http\Resources\Reference;

use App\Enums\Matter\Assignment;
use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** @property Assignment $resource */
class AssignmentCapacityResource extends ApiResource
{
    public function toId(Request $request): string
    {
        return $this->resource->value;
    }

    public function toType(Request $request): string
    {
        return 'assignment-capacities';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'name' => Str::headline($this->resource->value),
            // Held by one person at a time: assigning someone else replaces them.
            'exclusive' => $this->resource->isExclusive(),
        ];
    }
}
