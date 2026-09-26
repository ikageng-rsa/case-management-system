<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\Enums\Client\ContactKind;
use App\Models\Client;
use App\Rules\UniqueClientContact;
use App\Rules\ValidContactValue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The value is checked for shape and for being unique to this client, but
     * only once the kind is known — the kind decides what a valid value is.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $kind = ContactKind::tryFrom((string) $this->input('kind'));

        return [
            'kind' => ['required', Rule::enum(ContactKind::class)],
            'value' => array_values(array_filter([
                'required',
                'string',
                $kind !== null ? new ValidContactValue($kind) : null,
                $kind !== null ? new UniqueClientContact($this->client(), $kind) : null,
            ])),
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }

    /** The client this contact is being added to, resolved from the route. */
    protected function client(): Client
    {
        return $this->route('client');
    }
}
