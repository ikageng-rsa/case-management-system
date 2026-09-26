<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Client;
use App\Services\Clients\GenerateBlindIndex;

class ClientObserver
{
    /** Keep the searchable blind index in step with the encrypted ID number. */
    public function saving(Client $client): void
    {
        if (! $client->isDirty('id_number')) {
            return;
        }

        $client->id_number_hash = GenerateBlindIndex::of($client->id_number);
    }
}
