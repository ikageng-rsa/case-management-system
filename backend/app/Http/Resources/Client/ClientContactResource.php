<?php

declare(strict_types=1);

namespace App\Http\Resources\Client;

use App\Http\Resources\ApiResource;
use App\Models\Client\ClientContact;
use App\Services\Clients\MaskIdentifier;
use Illuminate\Http\Request;

/** @mixin ClientContact */
class ClientContactResource extends ApiResource
{
    public function toType(Request $request): string
    {
        return 'client-contacts';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'kind' => $this->kind->value,
            'value_masked' => MaskIdentifier::contact($this->kind, $this->value),
            'is_primary' => $this->is_primary,
        ];
    }
}
