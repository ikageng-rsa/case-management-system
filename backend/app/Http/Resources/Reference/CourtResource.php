<?php

declare(strict_types=1);

namespace App\Http\Resources\Reference;

use App\Http\Resources\ApiResource;
use App\Models\Reference\Court;
use Illuminate\Http\Request;

/** @mixin Court */
class CourtResource extends ApiResource
{
    public function toType(Request $request): string
    {
        return 'courts';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'seat' => $this->seat,
            'label' => $this->label,
            'tier' => $this->tier->value,
        ];
    }
}
