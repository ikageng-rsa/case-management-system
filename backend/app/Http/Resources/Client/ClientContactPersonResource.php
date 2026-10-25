<?php

declare(strict_types=1);

namespace App\Http\Resources\Client;

use App\Http\Resources\ApiResource;
use App\Models\Client\ClientContactPerson;
use App\Services\Clients\MaskIdentifier;
use Illuminate\Http\Request;

/**
 * Someone to speak to at an entity client. Their name is shown; how to reach them is masked.
 *
 * @mixin ClientContactPerson
 */
class ClientContactPersonResource extends ApiResource
{
    public function toType(Request $request): string
    {
        return 'client-contact-persons';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'role' => $this->role,
            'mobile_masked' => $this->mobile ? MaskIdentifier::mobile($this->mobile) : null,
            'email_masked' => $this->email ? MaskIdentifier::email($this->email) : null,
            'is_primary' => $this->is_primary,
        ];
    }
}
