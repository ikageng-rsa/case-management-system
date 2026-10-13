<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\Enums\Client\ContactKind;
use App\Rules\Client\ValidContactValue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveContactPersonRequest extends FormRequest
{
    protected $errorBag = 'person_new';

    protected function prepareForValidation(): void
    {
        // Every person has a sheet of their own, and only the one submitted should reopen.
        $person = $this->route('person');

        $this->errorBag = $person ? 'person_'.$person->getKey() : 'person_new';
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:100'],
            'mobile' => ['nullable', 'string', 'max:50', new ValidContactValue(ContactKind::Mobile)],
            'email' => ['nullable', 'string', 'max:255', new ValidContactValue(ContactKind::Email)],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * A person is only useful if they can be reached. When editing, what is
     * already on file counts, since an empty field keeps it.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $person = $this->route('person');

            $reachable = $this->filled('mobile') || $this->filled('email')
                || filled($person?->mobile) || filled($person?->email);

            if (! $reachable) {
                $validator->errors()->add('mobile', 'Give a mobile number or an email address.');
            }
        }];
    }
}
