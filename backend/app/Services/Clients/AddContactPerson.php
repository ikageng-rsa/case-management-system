<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Models\Client;
use App\Models\ClientContactPerson;
use Illuminate\Support\Facades\DB;

class AddContactPerson
{
    /**
     * The first person added is the one to call; later ones are alternatives
     * unless one is named the first contact, which moves the flag to them.
     *
     * @param  array{name: string, role?: ?string, mobile?: ?string, email?: ?string}  $data
     */
    public function add(Client $client, array $data, bool $isPrimary = false): ClientContactPerson
    {
        return DB::transaction(function () use ($client, $data, $isPrimary): ClientContactPerson {
            $isPrimary = $isPrimary || ! $client->contactPersons()->exists();

            if ($isPrimary) {
                $client->contactPersons()->update(['is_primary' => false]);
            }

            $person = new ClientContactPerson([
                'name' => trim($data['name']),
                'role' => filled($data['role'] ?? null) ? trim($data['role']) : null,
                'mobile' => $data['mobile'] ?? null,
                'email' => $data['email'] ?? null,
                'is_primary' => $isPrimary,
            ]);

            $person->client()->associate($client);
            $person->save();

            return $person;
        });
    }
}
