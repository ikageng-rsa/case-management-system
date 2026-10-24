<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Enums\Client\IdentityType;
use App\Models\Client;
use App\Support\Clients\NormaliseIdentifier;
use Carbon\CarbonImmutable;
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

            $this->updateIdentity($client, $data);
        }

        $identityChanges = collect(['id_number', 'date_of_birth'])->filter(fn (string $field) => $client->isDirty($field))->values();

        try {
            $client->save();
        } catch (UniqueConstraintViolationException) {
            throw new DuplicateClientException;
        }

        // Kept out of the model's own log so the values never reach it: what changed is said, never the value.
        if ($identityChanges->isNotEmpty()) {
            $client->recordEvent('identity_changed', 'Identity details changed on '.$client->auditLabel(), [
                'fields' => $identityChanges->map(fn (string $field) => str_replace('_', ' ', $field))->all(),
            ]);
        }

        return $client;
    }

    /**
     * The number is re-encrypted whenever it is assigned, so it is only assigned when it
     * is different. A South African ID that is new brings its date of birth with it,
     * unless one was given; a date given on its own simply replaces the one on file.
     *
     * @param  array<string, mixed>  $data
     */
    private function updateIdentity(Client $client, array $data): void
    {
        if (filled($data['id_number'] ?? null)) {
            $type = IdentityType::tryFrom((string) ($data['id_type'] ?? '')) ?? $client->id_type ?? IdentityType::SouthAfricanId;

            if (NormaliseIdentifier::identity($type, $data['id_number']) !== $client->id_number || $type !== $client->id_type) {
                $client->id_type = $type;
                $client->id_number = $data['id_number'];

                if ($type->isSouthAfrican() && blank($data['date_of_birth'] ?? null)) {
                    $client->date_of_birth = (new ResolveDateOfBirth)->fromSouthAfricanId(NormaliseIdentifier::idNumber($data['id_number']));
                }
            }
        }

        if (filled($data['date_of_birth'] ?? null)) {
            $client->date_of_birth = CarbonImmutable::parse($data['date_of_birth']);
        }
    }
}
