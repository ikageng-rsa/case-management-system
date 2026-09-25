<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\Enums\Client\ClientType;
use App\Rules\Client\UniqueClientIdNumber;
use App\Rules\Client\UniqueClientRegistrationNumber;
use App\Rules\Client\ValidRegistrationNumber;
use App\Rules\Client\ValidSouthAfricanId;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A client is either an individual with a SA ID or an entity with a registered
     * name and number the type decides which identity fields are required
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return match ($this->clientType()) {
            ClientType::Individual => $this->individualRules(),
            ClientType::Entity => $this->entityRules(),
            default => ['type' => ['required', Rule::enum(ClientType::class)]],
        };
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function individualRules(): array
    {
        return [
            'type' => ['required', Rule::enum(ClientType::class)],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'id_number' => ['required', 'string', new ValidSouthAfricanId, new UniqueClientIdNumber],
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function entityRules(): array
    {
        return [
            'type' => ['required', Rule::enum(ClientType::class)],
            'entity_name' => ['required', 'string', 'max:255'],
            'registration_number' => ['required', 'string', new ValidRegistrationNumber, new UniqueClientRegistrationNumber],
        ];
    }

    protected function clientType(): ?ClientType
    {
        return ClientType::tryFrom((string) $this->input('type'));
    }
}
