<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Models\ClientContact;
use Illuminate\Support\Facades\DB;

class RemoveClientContact
{
    /**
     * Contacts are removed outright; the audit log keeps the fact that it
     * happened. A client's last contact stays, and when the primary of a kind
     * goes, the oldest of that kind takes its place.
     */
    public function remove(ClientContact $contact): void
    {
        DB::transaction(function () use ($contact): void {
            $client = $contact->client;

            if ($client->contacts()->count() <= 1) {
                throw new CannotRemoveLastContact;
            }

            $wasPrimary = $contact->is_primary;

            $contact->delete();

            if ($wasPrimary) {
                $client->contacts()->ofKind($contact->kind)->oldest()->oldest('id')->first()?->update(['is_primary' => true]);
            }
        });
    }
}
