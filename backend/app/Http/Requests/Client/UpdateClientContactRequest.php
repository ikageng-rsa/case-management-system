<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\Rules\Client\UniqueClientContact;
use App\Rules\Client\ValidContactValue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClientContactRequest extends FormRequest
{
    protected $errorBag = 'contact_edit';

    protected function prepareForValidation(): void
    {
        // Every contact has a sheet of its own, and only the one submitted should reopen.
        $this->errorBag = 'contact_'.$this->route('contact')->getKey();
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A contact keeps its kind. The value is optional: left empty it is kept, so a
     * contact can be edited without being shown in full.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $contact = $this->route('contact');

        return [
            'value' => ['nullable', 'string', 'max:500', new ValidContactValue($contact->kind), new UniqueClientContact($contact->client, $contact->kind, $contact->getKey())],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
