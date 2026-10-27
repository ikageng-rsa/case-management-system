<?php

declare(strict_types=1);

namespace App\Http\Resources\Matter;

use App\Http\Resources\ApiResource;
use App\Models\Matter\MatterParty;
use Illuminate\Http\Request;

/**
 * Someone else in a matter: the other side, counsel, a witness.
 *
 * @mixin MatterParty
 */
class MatterPartyResource extends ApiResource
{
    public function toType(Request $request): string
    {
        return 'matter-parties';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'representative' => $this->representative,
        ];
    }
}
