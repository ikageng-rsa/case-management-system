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
        // Per client only — different clients may legitimately share a
        // contact (a family email, an entity's switchboard). The scope
        // normalises the value for this kind and blind-indexes it.
        $isDuplicate = $client->contacts()
            ->matchingValue($value, $contactKind)
            ->exists();

        if ($isDuplicate) {
            throw ValidationException::withMessages([
                'value' => "This {$contactKind->value} is already on file for the client.",
            ]);
        }

        $hasKind = $client->contacts()->ofKind($contactKind)->exists();

        if (! $client->isEntity() && $hasKind) {
            throw ValidationException::withMessages([
                'value' => "Individual clients can only have one {$contactKind->value} contact.",
            ]);
        }

        // The first contact of a kind is the primary by default.
        $isPrimary = $isPrimary || ! $hasKind;

        // Transaction: the saved hook demotes siblings in a second query.
        $contact = DB::transaction(fn () => $client->contacts()->create([
            'kind' => $contactKind,
            'value' => $value,
            'is_primary' => $isPrimary,
        ]));

        $client->unsetRelation('contacts');

        return $contact;
    }
}
