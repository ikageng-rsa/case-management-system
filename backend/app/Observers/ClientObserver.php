<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\Client\IdentityType;
use App\Models\Client;
use App\Support\Clients\GenerateBlindIndex;
use App\Support\Clients\NormaliseIdentifier;

class ClientObserver
{
    /** Keep the searchable blind index in step with the encrypted ID number. */
    public function saving(Client $client): void
    {
        if (! $client->isDirty('id_number') && ! $client->isDirty('id_type')) {
            return;
        }

        $client->id_number_hash = $client->id_number === null
            ? null
            : GenerateBlindIndex::of(NormaliseIdentifier::identityIndexKey($client->id_type ?? IdentityType::SouthAfricanId, $client->id_number));
    }
}
