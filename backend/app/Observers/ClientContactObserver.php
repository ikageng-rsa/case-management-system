<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ClientContact;
use App\Services\Clients\GenerateBlindIndex;

class ClientContactObserver
{
    /** Keep the searchable blind index in step with the encrypted value. */
    public function saving(ClientContact $contact): void
    {
        if (! $contact->isDirty('value')) {
            return;
        }

        $contact->value_hash = GenerateBlindIndex::of($contact->value);
    }

    /*
     * A client has at most one primary contact per kind, so promoting one
     * demotes whichever sibling of the same kind currently holds the flag.
     */
    public function saved(ClientContact $contact): void
    {
        if (! $contact->is_primary) {
            return;
        }

        ClientContact::query()
            ->where('client_id', $contact->client_id)
            ->where('kind', $contact->kind)
            ->whereKeyNot($contact->getKey())
            ->update(['is_primary' => false]);
    }
}
