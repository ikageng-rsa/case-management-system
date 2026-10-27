<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\Http\Requests\Client\Concerns\ValidatesIdentity;
use App\Models\Client\Client;
use App\Rules\Client\UniqueClientRegistrationNumber;
use App\Rules\Client\ValidRegistrationNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
{
    use ValidatesIdentity;

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
        ] + $this->identityRules($client->getKey());
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function ($validator): void {
            $client = $this->client();

            if ($client->isEntity()) {
                return;
            }

            // A different kind of document needs its number: the one on file is the other kind.
            if ($this->filled('id_type') && ! $this->filled('id_number') && $client->id_number !== null
                && $this->identityType() !== $client->id_type) {
                $validator->errors()->add('id_number', 'Enter the number to change the kind of document.');
            }

            $this->checkBirthDateAgainstId($validator, $client->id_number, $client->id_type);
        }];
    }

    protected function client(): Client
    {
        return $this->route('client');
    }
}
