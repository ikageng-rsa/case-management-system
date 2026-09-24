<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Enums\Client\ContactKind;
use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Support\Facades\DB;

class AddClientContact
{
    public function add(Client $client, ContactKind $contactKind, string $value, bool $isPrimary = false): ClientContact
    {
        $hasKind = $client->contacts()->ofKind($contactKind)->exists();

        if (! $client->isEntity() && $hasKind) {
            throw new ContactKindLimitException($contactKind);
        }

        $isPrimary = $isPrimary || ! $hasKind; // The first contact of a kind is the primary by default

        $contact = DB::transaction(fn () => $client->contacts()->create([
            'kind' => $contactKind,
            'value' => $value,
            'is_primary' => $isPrimary,
        ]));

        $client->unsetRelation('contacts');

        return $contact;
    }
}