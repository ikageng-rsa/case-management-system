<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Enums\Client\ClientType;
use App\Enums\Client\IdentityType;
use App\Models\Client;
use App\Support\Clients\NormaliseIdentifier;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use RuntimeException;

class RegisterClient
{
    /**
     * @param  array<string, mixed>  $data  Already shape-validated (FormRequest)
     */
    public function register(ClientType $type, array $data): Client
    {
        return match ($type) {
            ClientType::Individual => $this->registerIndividual($data),
            ClientType::Entity => $this->registerEntity($data)
        };
    }

    private function registerIndividual(array $data): Client
    {
        $number = filled($data['id_number'] ?? null) ? $data['id_number'] : null;
        $idType = $number === null ? null : (IdentityType::tryFrom((string) ($data['id_type'] ?? '')) ?? IdentityType::SouthAfricanId);

        if ($number !== null) {
            $existing = Client::withTrashed()->matchingIdNumber($number, $idType)->first();

            if ($existing) {
                throw new DuplicateClientException($existing);
            }
        }

        // The kind of document goes in before its number, which is normalised as what it is.
        return $this->create([
            'type' => ClientType::Individual,
            'id_type' => $idType,
            'id_number' => $number,
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'date_of_birth' => $this->dateOfBirth($data, $idType, $number),
        ]);
    }

    /** The date given, or failing that the one a South African ID number carries. */
    private function dateOfBirth(array $data, ?IdentityType $idType, ?string $number): ?CarbonInterface
    {
        if (filled($data['date_of_birth'] ?? null)) {
            return CarbonImmutable::parse($data['date_of_birth']);
        }

        return $idType?->isSouthAfrican()
            ? (new ResolveDateOfBirth)->fromSouthAfricanId(NormaliseIdentifier::idNumber($number))
            : null;
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
