<?php

declare(strict_types=1);

namespace App\Http\Resources\Client;

use App\Enums\Auth\Permission;
use App\Http\Resources\ApiResource;
use App\Models\Client\Client;
use App\Services\Clients\MaskIdentifier;
use Illuminate\Http\Request;

/**
 * A client as the app shows them: names in full, identifiers always masked. The
 * full value of any of them is read one at a time, through the reveal endpoint.
 *
 * @mixin Client
 */
class ClientResource extends ApiResource
{
    public function toType(Request $request): string
    {
        return 'clients';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'client_type' => $this->type->value,
            'name' => $this->full_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'entity_name' => $this->entity_name,
            'identity_type' => $this->id_type?->value,
            'identifier_masked' => $this->maskedIdentifier(),
            'date_of_birth_masked' => $this->date_of_birth ? MaskIdentifier::dateOfBirth() : null,
            'matters_count' => $this->whenCounted('matters'),
        ];
    }

    /**
     * Contacts are given only to those who may see them, whatever is asked to be included.
     *
     * @return array<string, class-string>
     */
    public function toRelationships(Request $request): array
    {
        if (! $request->user()?->can(Permission::ViewClientContacts->value)) {
            return [];
        }

        return [
            'contacts' => ClientContactResource::class,
            'contactPersons' => ClientContactPersonResource::class,
        ];
    }

    /** @return array<string, mixed> */
    public function toMeta(Request $request): array
    {
        $user = $request->user();

        return [
            'can' => [
                'view_contacts' => (bool) $user?->can(Permission::ViewClientContacts->value),
                'reveal' => (bool) $user?->can(Permission::ViewUnmaskedClientData->value),
            ],
        ];
    }

    private function maskedIdentifier(): ?string
    {
        if ($this->resource->isEntity()) {
            return $this->registration_number ? MaskIdentifier::registrationNumber($this->registration_number) : null;
        }

        return $this->id_number ? MaskIdentifier::idNumber($this->id_number) : null;
    }
}
