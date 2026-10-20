<?php

declare(strict_types=1);

namespace App\Http\Requests\Reference;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMatterTypeRequest extends FormRequest
{
    protected $errorBag = 'matter_type_new';

    protected function prepareForValidation(): void
    {
        $type = $this->route('matterType');

        $this->errorBag = $type ? 'matter_type_'.$type->getKey() : 'matter_type_new';

        // A code is written in capitals wherever it is typed.
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The code is optional when editing, because a type whose code is locked does
     * not send it. Retired types count for uniqueness: a code is never reused.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->route('matterType');

        return [
            'code' => [
                $type ? 'sometimes' : 'required',
                'string', 'regex:/^[A-Z]{2,8}$/',
                Rule::unique('matter_types', 'code')->ignore($type?->getKey()),
            ],
            'name' => ['required', 'string', 'max:255'],
            'default_prescription_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'is_litigation' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['code.regex' => 'A code is two to eight capital letters.'];
    }
}
