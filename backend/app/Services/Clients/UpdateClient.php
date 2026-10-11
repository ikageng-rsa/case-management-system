<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Models\Client;
use Illuminate\Database\UniqueConstraintViolationException;

class UpdateClient
{
    /**
     * Changes are written through the model in a single save, so the activity
     * log records them once and nothing is logged when nothing changed.
     *
     * @param  array<string, mixed>  $data  Already shape-validated (FormRequest)
     */
    public function update(Client $client, array $data): Client
    {
        if ($client->isEntity()) {
            $client->entity_name = trim($data['entity_name']);

            if (filled($data['registration_number'] ?? null)
                && NormaliseIdentifier::registrationNumber($data['registration_number']) !== $client->registration_number) {
                $client->registration_number = $data['registration_number'];
            }
        } else {
            $client->first_name = trim($data['first_name']);
            $client->last_name = trim($data['last_name']);

            // The ID number is re-encrypted whenever it is assigned, so it is
            // only assigned when it is different.
            if (filled($data['id_number'] ?? null)
                && NormaliseIdentifier::idNumber($data['id_number']) !== $client->id_number) {
                $client->id_number = $data['id_number'];
            }
        }

        try {
            $client->save();
        } catch (UniqueConstraintViolationException) {
            throw new DuplicateClientException;
        }

        return $client;
    }
}
