<?php

declare(strict_types=1);

namespace App\Http\Resources\Reference;

use App\Http\Resources\ApiResource;
use App\Models\Reference\MatterType;
use Illuminate\Http\Request;

/** @mixin MatterType */
class MatterTypeResource extends ApiResource
{
    public function toType(Request $request): string
    {
        return 'matter-types';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'is_litigation' => $this->is_litigation,
        ];
    }
}
