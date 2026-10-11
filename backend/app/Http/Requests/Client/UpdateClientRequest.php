<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\Models\Client;
use App\Rules\Client\UniqueClientIdNumber;
use App\Rules\Client\UniqueClientRegistrationNumber;
use App\Rules\Client\ValidRegistrationNumber;
use App\Rules\Client\ValidSouthAfricanId;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
{
    protected $errorBag = 'client_edit';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The type of a client is fixed, so the type decides which identity fields
     * are edited. An identifier left empty is left as it is.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $client = $this->route('client');

        if ($client->isEntity()) {
            return [
                'entity_name' => ['required', 'string', 'max:255'],
                'registration_number' => ['nullable', 'string', new ValidRegistrationNumber, new UniqueClientRegistrationNumber($client->getKey())],
            ];
        }

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'id_number' => ['nullable', 'string', new ValidSouthAfricanId, new UniqueClientIdNumber($client->getKey())],
        ];
    }

    protected function client(): Client
    {
        return $this->route('client');
    }
}
