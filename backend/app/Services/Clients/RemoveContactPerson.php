<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Models\Client\ClientContactPerson;
use Illuminate\Support\Facades\DB;

class RemoveContactPerson
{
    /** Removed outright; when the first contact goes, the longest-standing alternative takes over. */
    public function remove(ClientContactPerson $person): void
    {
        DB::transaction(function () use ($person): void {
            $client = $person->client;
            $wasPrimary = $person->is_primary;

            $person->delete();

            if ($wasPrimary) {
                $client->contactPersons()->oldest()->oldest('id')->first()?->update(['is_primary' => true]);
            }
        });
    }
}
