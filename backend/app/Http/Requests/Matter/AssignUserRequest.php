<?php

declare(strict_types=1);

namespace App\Http\Requests\Matter;

use App\Enums\Matter\Assignment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignUserRequest extends FormRequest
{
    protected $errorBag = 'assignment';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', Rule::exists('users', 'id')],
            'capacity' => ['required', Rule::enum(Assignment::class)],
        ];
    }
}
