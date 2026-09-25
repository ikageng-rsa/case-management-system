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
    public function handle(Client $client, ContactKind $contactKind, string $value, bool $isPrimary = false): ClientContact
    {
        $value = NormaliseIdentifier::contact($contactKind, $value);
        $this->assertWellFormed($contactKind, $value);

        if ($client->contacts()->matchingValue($value, $contactKind)->exists()) {
            throw ValidationException::withMessages(['value' => 'This contact is already in the system',]);
        }
        $hasKind = $client->contacts()->ofKind($contactKind)->exists();
        if (! $client->isEntity() && $hasKind) {
            throw ValidationException::withMessages(['value' => "Individual clients can only have one {$contactKind->value} contact."]);
        }
        // THE First contact of a kind is the primary by default.
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

    // The duplicate check is per client only. Different clients can legitimately share a contact, such as a family email or an entity's switchboard.
    private function assertWellFormed(ContactKind $kind, string $value): void
    {
        $valid = $kind == ContactKind::Mobile
            ? (bool) preg_match('/^\+\d{8,15}$/', $value)
            : (bool) filter_var($value, FILTER_VALIDATE_EMAIL);

        if (! $valid) {
            throw ValidationException::withMessages(['value' => "Invalid {$kind->value}."]);
        }
    }
}