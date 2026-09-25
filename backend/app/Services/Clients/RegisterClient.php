<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Enums\Client\ClientType;
use App\Models\Client;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RegisterClient
{
    /**
     * @param  array<string, mixed>  $data  Already shape-validated (FormRequest)
     */
    public function handle(ClientType $type, array $data): Client
    {
        return match ($type) {
            ClientType::Individual => $this->registerIndividual($data),
            ClientType::Entity => $this->registerEntity($data)
        };
    }

    private function registerIndividual(array $data): Client
    {
        $idNumber = NormaliseIdentifier::idNumber($data['id_number']);

        $existingClient = Client::withTrashed()->matchingIdNumber($idNumber)->first();

        if ($existingClient) {
            throw new DuplicateClientException($existingClient);
        }

        return Client::create([
            'id_number' => $idNumber,
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'type' => ClientType::Individual,
        ]);
    }

    private function registerEntity(array $data): Client
    {
        $registration = NormaliseIdentifier::registrationNumber($data['registration_number']);

        // Deliberately permissive: trusts, NPCs and older companies don't all
        // follow the 2020/123456/07 pattern, and rejecting a real client is worse than a typo.

        if (! preg_match('#^[A-Z0-9/\-]{5,25}$#', $registration)) {
            throw ValidationException::withMessages(['registration_number' => 'The registration number is not valid.']);
        }

        $existing = Client::withTrashed()->where('registration_number', $registration)->first();
        if ($existing) {
            throw new DuplicateClientException($existing);
        }

        return $this->create([
            'type' => ClientType::Entity,
            'entity_name' => trim($data['entity_name']),
            'registration_number' => $registration,
        ]);
    }

    private function create(array $attributes): Client
    {
        try {
            return Client::create($attributes);
        } catch (UniqueConstraintViolationException) {
            throw new DuplicateClientException;
        }
    }
}

final class DuplicateClientException extends RuntimeException
{
    public function __construct(public readonly ?Client $existing = null)
    {
        parent::__construct('A client with this identifier is already registered.');
    }
}
