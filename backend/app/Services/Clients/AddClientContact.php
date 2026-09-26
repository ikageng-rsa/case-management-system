<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Enums\Client\ContactKind;
use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddClientContact
{
    public function add(Client $client, ContactKind $contactKind, string $value, bool $isPrimary = false): ClientContact
    {
        // The duplicate check is per client only. Different clients can legitimately
        // share a contact, such as a family email or an entity's switchboard.
        if ($client->contacts()->matchingValue($value, $contactKind)->exists()) {
            throw ValidationException::withMessages(['value' => 'This contact is already in the system']);
        }
        $hasKind = $client->contacts()->ofKind($contactKind)->exists();
        if (! $client->isEntity() && $hasKind) {
            throw ValidationException::withMessages(['value' => "Individual clients can only have one {$contactKind->value} contact."]);
        }

        $isPrimary = $isPrimary || ! $hasKind;

        $contact = DB::transaction(fn () => $client->contacts()->create([
            'kind' => $contactKind,
            'value' => $value,
            'is_primary' => $isPrimary,
        ]));

        $client->unsetRelation('contacts');

        return $contact;
    }
}
