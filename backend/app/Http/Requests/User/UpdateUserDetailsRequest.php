<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Enums\Client\ContactKind;
use App\Rules\Client\ValidContactValue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserDetailsRequest extends FormRequest
{
    protected $errorBag = 'user_details';

    protected function prepareForValidation(): void
    {
        // Every person has a sheet of their own, and only the one submitted should reopen.
        $this->errorBag = 'user_details_'.$this->route('user')->getKey();
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The staff record. An email or phone number left empty keeps the one on
     * file, so the record can be edited without them being shown in full.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->getKey())],
            'job_title' => ['nullable', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:50', new ValidContactValue(ContactKind::Mobile)],
            'office_phone' => ['nullable', 'string', 'max:50', new ValidContactValue(ContactKind::Mobile)],
            'employee_number' => ['nullable', 'string', 'max:50', Rule::unique('users', 'employee_number')->ignore($user->getKey())],
        ];
    }
}
