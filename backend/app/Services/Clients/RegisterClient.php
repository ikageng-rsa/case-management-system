<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Enums\Client\ClientType;
use App\Models\Client;
use Illuminate\Database\UniqueConstraintViolationException;
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
        $existing = Client::withTrashed()->matchingIdNumber($data['id_number'])->first();

        if ($existing) {
            throw new DuplicateClientException($existing);
        }

        return Client::create([
            'id_number' => $data['id_number'],
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'type' => ClientType::Individual,
        ]);
    }

    private function registerEntity(array $data): Client
    {
        $existing = Client::withTrashed()->matchingRegistrationNumber($data['registration_number'])->first();

        if ($existing) {
            throw new DuplicateClientException($existing);
        }

        return $this->create([
            'type' => ClientType::Entity,
            'entity_name' => trim($data['entity_name']),
            'registration_number' => $data['registration_number'],
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
