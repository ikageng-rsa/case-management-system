<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeactivateUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Each person has a sheet of their own, and only the one submitted should reopen.
        $this->errorBag = 'deactivate_user_'.$this->route('user')->getKey();
    }

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
            'reason' => ['required', 'string', 'max:500'],
            'successors' => ['nullable', 'array'],
            'successors.*' => ['nullable', Rule::exists('users', 'id')->whereNull('deleted_at')],
        ];
    }
}
